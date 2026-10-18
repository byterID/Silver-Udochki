<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use RuntimeException;

/** Операция по кошельку невозможна. Текст сообщения можно показывать пользователю. */
final class WalletException extends RuntimeException {}
