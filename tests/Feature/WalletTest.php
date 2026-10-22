<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WalletReason;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletException;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shop.welcome_balance' => 20_000]);
    }

    private function keeper(): WalletKeeper
    {
        return app(WalletKeeper::class);
    }

    public function test_регистрация_открывает_кошелёк_с_20000(): void
    {
        $this->post(route('register'), [
            'name' => 'Рыбак',
            'email' => 'fisher@example.com',
            'password' => 'password12345',
            'password_confirmation' => 'password12345',
        ])->assertRedirect();

        $user = User::query()->where('email', 'fisher@example.com')->firstOrFail();

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 20_000]);
        $this->assertDatabaseHas('wallet_transactions', [
            'amount' => 20_000, 'balance_after' => 20_000, 'reason' => 'welcome', 'reference' => 'welcome',
        ]);
    }

    public function test_повторное_открытие_не_начисляет_второй_раз(): void
    {
        $user = User::factory()->create();

        $this->keeper()->open($user);
        $this->keeper()->open($user->fresh() ?? $user);

        $this->assertDatabaseCount('wallets', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(20_000, $this->keeper()->walletOf($user)->balance);
    }

    public function test_кошелёк_без_регистрации_открывается_при_первом_обращении(): void
    {
        $user = User::factory()->create();
        $this->assertDatabaseCount('wallets', 0);

        $this->assertSame(20_000, $this->keeper()->walletOf($user)->balance);
    }

    public function test_списание_и_начисление_пишут_журнал(): void
    {
        $user = User::factory()->create();
        $wallet = $this->keeper()->walletOf($user);

        $debit = $this->keeper()->debit($user, 1_500, WalletReason::Purchase, note: 'проверка');
        $this->assertSame([-1_500, 18_500], [$debit->amount, $debit->balance_after]);
        $this->assertSame(18_500, $wallet->balance, 'Экземпляр кошелька у вызывающего обновлён');

        $credit = $this->keeper()->credit($user, 500, WalletReason::Refund);
        $this->assertSame([500, 19_000], [$credit->amount, $credit->balance_after]);
        $this->assertSame(19_000, Wallet::query()->value('balance'));
    }

    public function test_в_минус_уйти_нельзя(): void
    {
        $user = User::factory()->create();
        $this->keeper()->walletOf($user);

        try {
            $this->keeper()->debit($user, 20_001, WalletReason::Purchase);
            $this->fail('Ожидалось исключение');
        } catch (WalletException $e) {
            $this->assertStringContainsString('Недостаточно денег', $e->getMessage());
            $this->assertSame(20_000, Wallet::query()->value('balance'));
            $this->assertDatabaseCount('wallet_transactions', 1);
        }
    }

    public function test_операция_с_тем_же_ключом_проводится_один_раз(): void
    {
        $user = User::factory()->create();

        $first = $this->keeper()->debit($user, 700, WalletReason::Purchase, 'order:1');
        $again = $this->keeper()->debit($user, 700, WalletReason::Purchase, 'order:1');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(19_300, $this->keeper()->walletOf($user)->balance);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_нулевая_сумма_отклоняется(): void
    {
        $this->expectException(WalletException::class);

        $this->keeper()->credit(User::factory()->create(), 0, WalletReason::Refund);
    }

    public function test_миграция_начисляет_тем_кто_зарегистрировался_раньше(): void
    {
        $old = User::factory()->create();
        $withWallet = User::factory()->create();
        $this->keeper()->debit($withWallet, 5_000, WalletReason::Purchase);

        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_19_120100_grant_welcome_balance.php');
        $migration->up(); // @phpstan-ignore method.notFound

        $this->assertDatabaseHas('wallets', ['user_id' => $old->id, 'balance' => 20_000]);
        $this->assertDatabaseHas('wallets', ['user_id' => $withWallet->id, 'balance' => 15_000]);
        $this->assertDatabaseCount('wallets', 2);
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_шапка_показывает_баланс_и_историю(): void
    {
        $user = User::factory()->create();
        $this->keeper()->debit($user, 1_234, WalletReason::Purchase, note: 'проверка');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Баланс')
            ->assertSee('18 766 ₽')
            ->assertSee('История операций')
            ->assertSee('+20 000 ₽')
            ->assertSee('−1 234 ₽')
            ->assertSee('проверка');
    }

    public function test_гостю_кошелёк_не_открывается(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('Баланс');

        $this->assertDatabaseCount('wallets', 0);
    }
}
