<?php

declare(strict_types=1);

namespace App\Services\Game;

use RuntimeException;

/** Выстрелы не могли быть сделаны настоящим ружьём: раунд закрывается без награды. */
class ShotsRejected extends RuntimeException {}
