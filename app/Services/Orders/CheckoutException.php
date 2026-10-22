<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Services\Pricing\CartQuote;
use RuntimeException;
use Throwable;

// Сообщение показывается покупателю. $quote — свежий расчёт, если корзина разошлась с сервером
final class CheckoutException extends RuntimeException
{
    public function __construct(string $message, public readonly ?CartQuote $quote = null, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
