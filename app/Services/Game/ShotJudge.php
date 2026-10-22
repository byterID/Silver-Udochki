<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Models\GameRound;

// От браузера берём только время и координаты выстрелов, попадания считаем сами.
// position() и выбор цели повторены в duck-game.js
/**
 * @phpstan-import-type Plan from GameRound
 * @phpstan-import-type Duck from GameRound
 *
 * @phpstan-type Shot array{t: int, x: float, y: float}
 */
class ShotJudge
{
    /**
     * @param  Plan  $plan
     * @param  list<Shot>  $shots
     * @return array{hits: list<int>, score: int}
     *
     * @throws ShotsRejected
     */
    public function judge(array $plan, array $shots): array
    {
        $this->checkGun($plan, $shots);

        $hit = [];
        $score = 0;

        foreach ($shots as $shot) {
            $target = null;
            $nearest = INF;

            // Один выстрел — одна утка: ближайшая к прицелу из ещё не подбитых
            foreach ($plan['ducks'] as $duck) {
                if (isset($hit[$duck['id']])) {
                    continue;
                }

                $at = self::position($duck['path'], $shot['t']);
                if ($at === null) {
                    continue;
                }

                $distance = hypot($at[0] - $shot['x'], $at[1] - $shot['y']);
                if ($distance <= $duck['radius'] && $distance < $nearest) {
                    $target = $duck;
                    $nearest = $distance;
                }
            }

            if ($target !== null) {
                $hit[$target['id']] = true;
                $score += $target['points'];
            }
        }

        return ['hits' => array_keys($hit), 'score' => $score];
    }

    // null — ещё не взлетела или уже улетела
    /**
     * @param  list<array{0: int, 1: int, 2: int}>  $path
     * @return array{0: float, 1: float}|null
     */
    public static function position(array $path, int $t): ?array
    {
        $last = count($path) - 1;
        if ($t < $path[0][0] || $t > $path[$last][0]) {
            return null;
        }

        for ($i = 0; $i < $last; $i++) {
            [$t0, $x0, $y0] = $path[$i];
            [$t1, $x1, $y1] = $path[$i + 1];

            if ($t <= $t1) {
                $f = ($t - $t0) / ($t1 - $t0);

                return [$x0 + ($x1 - $x0) * $f, $y0 + ($y1 - $y0) * $f];
            }
        }

        return null;
    }

    /**
     * @param  Plan  $plan
     * @param  list<Shot>  $shots
     *
     * @throws ShotsRejected
     */
    private function checkGun(array $plan, array $shots): void
    {
        $shells = $plan['magazine'];
        $previous = null;

        foreach ($shots as $shot) {
            if ($shot['t'] > $plan['duration_ms']) {
                throw new ShotsRejected('Выстрел после конца раунда.');
            }
            if ($shot['x'] < 0 || $shot['x'] > $plan['field']['width'] || $shot['y'] < 0 || $shot['y'] > $plan['field']['height']) {
                throw new ShotsRejected('Выстрел мимо поля.');
            }

            if ($previous !== null) {
                $gap = $shot['t'] - $previous;

                if ($gap < 0) {
                    throw new ShotsRejected('Выстрелы пришли не по порядку.');
                }
                if ($gap < $plan['min_interval_ms']) {
                    throw new ShotsRejected('Так часто двустволка не стреляет.');
                }
                if ($gap >= $plan['reload_ms']) {
                    $shells = $plan['magazine'];
                }
            }

            if ($shells === 0) {
                throw new ShotsRejected('Выстрел без перезарядки.');
            }

            $shells--;
            $previous = $shot['t'];
        }
    }
}
