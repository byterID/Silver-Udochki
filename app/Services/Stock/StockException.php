<?php

declare(strict_types=1);

namespace App\Services\Stock;

use RuntimeException;

/** Изменение остатка невозможно. Текст сообщения можно показывать пользователю. */
final class StockException extends RuntimeException {}
