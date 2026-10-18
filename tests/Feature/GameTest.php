<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DuckKind;
use App\Enums\GameRoundStatus;
use App\Enums\WalletReason;
use App\Models\GameRound;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Game\FlightPlanner;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tests\TestCase;

/** Мини-игра: раскладка полётов, проверка выстрелов на сервере и награда на кошелёк. */
class GameTest extends TestCase
{
    use RefreshDatabase;

    private const WELCOME = 20_000;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shop.welcome_balance' => self::WELCOME, 'game.daily_limit' => 3000, 'game.grace_seconds' => 120]);
        $this->travelTo('2026-10-19 08:00:00');
    }

    /**
     * Кряква в t=2000 ровно в (200, 200), золотой селезень в t=3000 — в (700, 200).
     *
     * @return array<string, mixed>
     */
    private function plan(): array
    {
        return [
            'field' => ['width' => 960, 'height' => 540, 'horizon' => 380],
            'duration_ms' => 60_000,
            'magazine' => 2,
            'reload_ms' => 900,
            'min_interval_ms' => 120,
            'rate' => 25,
            'ducks' => [
                ['id' => 0, 'kind' => 'mallard', 'points' => 1, 'radius' => 34, 'path' => [[1000, 100, 300], [3000, 300, 100]]],
                ['id' => 1, 'kind' => 'golden', 'points' => 5, 'radius' => 25, 'path' => [[2000, 800, 300], [4000, 600, 100]]],
            ],
        ];
    }

    private function round(User $user, GameRoundStatus $status = GameRoundStatus::Playing): GameRound
    {
        return $user->gameRounds()->create(['status' => $status, 'plan' => $this->plan(), 'started_at' => now()]);
    }

    /**
     * @param  list<array{0: int, 1: float|int, 2: float|int}>  $shots
     * @return TestResponse<JsonResponse>
     */
    private function finish(User $user, GameRound $round, array $shots): TestResponse
    {
        return $this->actingAs($user)->postJson(route('game.rounds.finish', $round), [
            'shots' => array_map(fn (array $s) => ['t' => $s[0], 'x' => $s[1], 'y' => $s[2]], $shots),
        ]);
    }

    private function balance(User $user): int
    {
        return app(WalletKeeper::class)->walletOf($user->fresh())->balance;
    }

    public function test_страница_только_для_вошедших(): void
    {
        $this->get(route('game.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('game.index'))
            ->assertOk()
            ->assertSee('Утиная зорька')
            ->assertSee('Золотой селезень');
    }

    public function test_старт_выдаёт_полёты_и_закрывает_прежний_раунд(): void
    {
        $user = User::factory()->create();
        $old = $this->round($user);

        $response = $this->actingAs($user)->postJson(route('game.rounds.store'))
            ->assertCreated()
            ->assertJsonCount((int) config('game.ducks'), 'plan.ducks');

        $round = GameRound::query()->findOrFail($response->json('id'));
        $this->assertSame(GameRoundStatus::Playing, $round->status);
        $this->assertSame(route('game.rounds.finish', $round), $response->json('finish_url'));
        $this->assertSame(GameRoundStatus::Abandoned, $old->refresh()->status);

        $this->finish($user, $old, [])->assertStatus(409);
    }

    public function test_раскладка_укладывается_в_раунд(): void
    {
        $durationMs = (int) config('game.duration') * 1000;
        ['width' => $width, 'horizon' => $horizon] = config('game.field');

        foreach (range(1, 40) as $seed) {
            $plan = app(FlightPlanner::class)->plan(new Randomizer(new Xoshiro256StarStar($seed)));
            $spawns = array_map(fn (array $d) => $d['path'][0][0], $plan['ducks']);

            $this->assertCount((int) config('game.ducks'), $plan['ducks']);
            $this->assertSame($spawns, collect($spawns)->sort()->values()->all(), 'утки взлетают по порядку');
            $this->assertSame(range(0, count($plan['ducks']) - 1), array_column($plan['ducks'], 'id'));

            foreach ($plan['ducks'] as $duck) {
                $kind = DuckKind::from($duck['kind']);
                $times = array_column($duck['path'], 0);
                $last = $duck['path'][array_key_last($duck['path'])];

                $this->assertSame([$kind->points(), $kind->radius()], [$duck['points'], $duck['radius']]);
                $this->assertGreaterThanOrEqual(FlightPlanner::FIRST_MS, $times[0]);
                $this->assertLessThanOrEqual($durationMs - FlightPlanner::TAIL_MS, end($times));
                $this->assertSame($horizon + 15, $duck['path'][0][2], 'взлёт из камыша');
                $this->assertTrue($last[1] < 0 || $last[1] > $width || $last[2] < 0, 'улетает за край');
                for ($i = 1; $i < count($times); $i++) {
                    $this->assertGreaterThan($times[$i - 1], $times[$i]);
                }
            }
        }
    }

    public function test_попадания_начисляют_деньги_один_раз(): void
    {
        $user = User::factory()->create();
        $round = $this->round($user);
        $this->travel(61)->seconds();

        $shots = [[1500, 500, 500], [2000, 205, 195], [3000, 700, 210]]; // промах, кряква, селезень
        $this->finish($user, $round, $shots)
            ->assertOk()
            ->assertJson(['hits' => 2, 'ducks' => 2, 'score' => 6, 'reward' => 150, 'daily_left' => 2850, 'balance' => self::WELCOME + 150]);

        // Повтор (например, после обрыва связи) отдаёт тот же итог и денег не добавляет
        $this->finish($user, $round, $shots)->assertOk()->assertJson(['reward' => 150, 'balance' => self::WELCOME + 150]);

        $round->refresh();
        $this->assertSame(GameRoundStatus::Finished, $round->status);
        $this->assertSame([3, 2, 6, 150], [$round->shots, $round->hits, $round->score, $round->reward]);

        $operation = WalletTransaction::query()->where('reason', WalletReason::Game)->sole();
        $this->assertSame(150, $operation->amount);
        $this->assertSame('game:'.$round->id, $operation->reference);
    }

    public function test_одна_утка_засчитывается_одним_выстрелом(): void
    {
        $user = User::factory()->create();
        $round = $this->round($user);
        $this->travel(61)->seconds();

        // Второй выстрел по уже подбитой крякве ничего не даёт, хотя пришёлся бы точно в неё
        $this->finish($user, $round, [[2000, 200, 200], [2200, 210, 190]])
            ->assertOk()
            ->assertJson(['hits' => 1, 'score' => 1, 'reward' => 25]);
    }

    public function test_утка_до_взлёта_и_после_ухода_неуязвима(): void
    {
        $user = User::factory()->create();
        $round = $this->round($user);
        $this->travel(61)->seconds();

        $this->finish($user, $round, [[999, 100, 300], [4100, 600, 100]])
            ->assertOk()
            ->assertJson(['hits' => 0, 'reward' => 0, 'balance' => self::WELCOME]);
        $this->assertSame(0, WalletTransaction::query()->where('reason', WalletReason::Game)->count());
    }

    public function test_перезарядка_по_паузе(): void
    {
        $user = User::factory()->create();
        $round = $this->round($user);
        $this->travel(61)->seconds();

        // Два выстрела, пауза 900 мс на перезарядку, ещё два
        $this->finish($user, $round, [[1000, 1, 1], [1200, 1, 1], [2100, 1, 1], [2300, 1, 1]])
            ->assertOk()
            ->assertJson(['hits' => 0]);
    }

    /** @return array<string, array{0: list<array{0: int, 1: int, 2: int}>}> */
    public static function impossibleShots(): array
    {
        return [
            'третий выстрел без перезарядки' => [[[1000, 1, 1], [1200, 1, 1], [1400, 1, 1]]],
            'чаще min_interval' => [[[1000, 1, 1], [1050, 1, 1]]],
            'не по порядку' => [[[2000, 1, 1], [1000, 1, 1]]],
            'после конца раунда' => [[[60_001, 1, 1]]],
            'за краем поля' => [[[1000, 961, 10]]],
        ];
    }

    /** @param  list<array{0: int, 1: int, 2: int}>  $shots */
    #[DataProvider('impossibleShots')]
    public function test_невозможные_выстрелы_закрывают_раунд_без_награды(array $shots): void
    {
        $user = User::factory()->create();
        $round = $this->round($user);
        $this->travel(61)->seconds();

        // Даже с честным попаданием в крякву в начале: пауза после него больше перезарядки
        $this->finish($user, $round, [[2000, 200, 200], ...array_map(fn ($s) => [$s[0] + 3000, $s[1], $s[2]], $shots)])
            ->assertUnprocessable();

        $this->assertSame(GameRoundStatus::Rejected, $round->refresh()->status);
        $this->assertSame(0, $round->reward);
        $this->assertSame(self::WELCOME, $this->balance($user));
    }

    public function test_итог_раньше_конца_раунда_не_принимается(): void
    {
        $user = User::factory()->create();
        $round = $this->round($user);
        $this->travel(50)->seconds();

        $this->finish($user, $round, [[2000, 200, 200]])->assertStatus(425);
        $this->assertSame(GameRoundStatus::Playing, $round->refresh()->status);

        // Сетевую задержку прощаем: за 2 секунды до конца уже можно
        $this->travel(9)->seconds();
        $this->finish($user, $round, [[2000, 200, 200]])->assertOk()->assertJson(['reward' => 25]);
    }

    public function test_опоздавший_итог_сгорает(): void
    {
        $user = User::factory()->create();
        $round = $this->round($user);
        $this->travel(60 + 121)->seconds();

        $this->finish($user, $round, [[2000, 200, 200]])->assertStatus(410);
        $this->assertSame(GameRoundStatus::Expired, $round->refresh()->status);
        $this->assertSame(self::WELCOME, $this->balance($user));
    }

    public function test_суточный_лимит(): void
    {
        config(['game.daily_limit' => 100]);
        $user = User::factory()->create();

        // Вчерашний выигрыш вне окна 24 часов, сегодняшний — внутри
        $user->gameRounds()->create(['status' => GameRoundStatus::Finished, 'plan' => $this->plan(), 'started_at' => now()])
            ->forceFill(['reward' => 500, 'finished_at' => now()->subHours(25)])->save();
        $user->gameRounds()->create(['status' => GameRoundStatus::Finished, 'plan' => $this->plan(), 'started_at' => now()])
            ->forceFill(['reward' => 80, 'finished_at' => now()->subHour()])->save();

        $round = $this->round($user);
        $this->travel(61)->seconds();

        $this->finish($user, $round, [[2000, 200, 200], [3000, 700, 200]])
            ->assertOk()
            ->assertJson(['score' => 6, 'reward' => 20, 'daily_left' => 0, 'balance' => self::WELCOME + 20]);

        // Лимит исчерпан: очки считаются, денег нет
        $next = $this->round($user);
        $this->travel(61)->seconds();
        $this->finish($user, $next, [[2000, 200, 200]])->assertOk()->assertJson(['score' => 1, 'reward' => 0]);
    }

    public function test_чужой_раунд_и_кривые_данные(): void
    {
        [$owner, $stranger] = User::factory()->count(2)->create();
        $round = $this->round($owner);
        $this->travel(61)->seconds();

        $this->finish($stranger, $round, [])->assertForbidden();

        $this->actingAs($owner)->postJson(route('game.rounds.finish', $round), ['shots' => [['t' => -1, 'x' => 'a']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shots.0.t', 'shots.0.x', 'shots.0.y']);
        $this->assertSame(GameRoundStatus::Playing, $round->refresh()->status);
    }
}
