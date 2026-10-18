<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /** Возврат оформляет только сам покупатель. Сроки и статус проверяет OrderRefunder. */
    public function refund(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
