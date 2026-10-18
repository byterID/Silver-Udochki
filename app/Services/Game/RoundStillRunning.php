<?php

declare(strict_types=1);

namespace App\Services\Game;

use RuntimeException;

/** Итог прислали, пока утки ещё в воздухе. Раунд не меняется, итог можно прислать позже. */
class RoundStillRunning extends RuntimeException {}
