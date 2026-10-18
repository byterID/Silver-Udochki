<?php

declare(strict_types=1);

namespace App\Services\Orders;

use RuntimeException;

/** Возврат не оформлен, ничего не изменилось. Текст сообщения можно показывать пользователю. */
final class ReturnException extends RuntimeException {}
