<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\DuckKind;
use App\Models\GameRound;
use Random\Randomizer;

// Браузер рисует раскладку целиком, ShotJudge по ней же проверяет выстрелы
/** @phpstan-import-type Plan from GameRound */
class FlightPlanner
{
    public const FIRST_MS = 1500;

    public const TAIL_MS = 1000;

    /** @return Plan */
    public function plan(?Randomizer $random = null): array
    {
        $random ??= new Randomizer;
        ['width' => $width, 'height' => $height, 'horizon' => $horizon] = config('game.field');
        $durationMs = (int) config('game.duration') * 1000;
        $count = max(1, (int) config('game.ducks'));

        $window = $durationMs - self::FIRST_MS - self::TAIL_MS;
        $step = intdiv($window, $count);
        $jitter = intdiv($step * 35, 100);

        $ducks = [];
        for ($i = 0; $i < $count; $i++) {
            $kind = $this->pickKind($random);
            $path = $this->flight($random, $kind, (int) $width, (int) $horizon, $window);
            $flight = $path[array_key_last($path)][0];

            // Утки взлетают по очереди с разбросом, иногда почти парой
            $spawn = self::FIRST_MS + $i * $step + $random->getInt(-$jitter, $jitter);
            $spawn = max(self::FIRST_MS, min($spawn, $durationMs - self::TAIL_MS - $flight));

            $ducks[] = [
                'kind' => $kind,
                'path' => array_map(fn (array $p) => [$p[0] + $spawn, $p[1], $p[2]], $path),
            ];
        }

        usort($ducks, fn (array $a, array $b) => $a['path'][0][0] <=> $b['path'][0][0]);

        return [
            'field' => ['width' => (int) $width, 'height' => (int) $height, 'horizon' => (int) $horizon],
            'duration_ms' => $durationMs,
            'magazine' => (int) config('game.magazine'),
            'reload_ms' => (int) config('game.reload_ms'),
            'min_interval_ms' => (int) config('game.min_interval_ms'),
            'rate' => (int) config('game.rate'),
            'ducks' => array_map(fn (array $duck, int $id) => [
                'id' => $id,
                'kind' => $duck['kind']->value,
                'points' => $duck['kind']->points(),
                'radius' => $duck['kind']->radius(),
                'path' => $duck['path'],
            ], $ducks, array_keys($ducks)),
        ];
    }

    private function pickKind(Randomizer $random): DuckKind
    {
        $roll = $random->getInt(1, array_sum(array_map(fn (DuckKind $k) => $k->weight(), DuckKind::cases())));

        foreach (DuckKind::cases() as $kind) {
            $roll -= $kind->weight();
            if ($roll <= 0) {
                return $kind;
            }
        }

        return DuckKind::Mallard;
    }

    // Точки [мс от взлёта, x, y]: взлёт из камыша, повороты, уход за край поля
    /** @return list<array{0: int, 1: int, 2: int}> */
    private function flight(Randomizer $random, DuckKind $kind, int $width, int $horizon, int $maxMs): array
    {
        $points = [[$random->getInt(80, $width - 80), $horizon + 15]];

        for ($turns = $random->getInt(3, 5); $turns > 0; $turns--) {
            [$x] = $points[array_key_last($points)];
            $points[] = [
                max(60, min($width - 60, $x + $random->getInt(-340, 340))),
                $random->getInt(50, $horizon - 70),
            ];
        }

        [$x] = $points[array_key_last($points)];
        $points[] = match ($random->getInt(0, 2)) {
            0 => [max(40, min($width - 40, $x + $random->getInt(-200, 200))), -60],
            1 => [-60, $random->getInt(40, intdiv($horizon, 2))],
            default => [$width + 60, $random->getInt(40, intdiv($horizon, 2))],
        };

        // В коротком раунде убираем повороты, пока утка не успеет улететь
        $path = $this->timed($points, $kind->speed());
        while ($path[array_key_last($path)][0] > $maxMs && count($points) > 2) {
            array_splice($points, 1, 1);
            $path = $this->timed($points, $kind->speed());
        }

        return $path;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $points
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private function timed(array $points, int $speed): array
    {
        $t = 0;
        $path = [[0, $points[0][0], $points[0][1]]];

        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $distance = hypot($points[$i][0] - $points[$i - 1][0], $points[$i][1] - $points[$i - 1][1]);
            $t += max(50, (int) round($distance / $speed * 1000));
            $path[] = [$t, $points[$i][0], $points[$i][1]];
        }

        return $path;
    }
}
