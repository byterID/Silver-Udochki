<?php

declare(strict_types=1);

namespace App\Enums;

/** Причина операции по кошельку: пишется в журнал wallet_transactions. */
enum WalletReason: string
{
    /** Приветственные деньги при регистрации. */
    case Welcome = 'welcome';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Подарок при регистрации',
        };
    }
}
