<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Services\Pricing\CartQuote;
use RuntimeException;
use Throwable;

/**
 * Заказ не оформлен, ничего не списано. Текст сообщения можно показывать пользователю.
 * $quote есть, когда корзина разошлась с тем, что видел покупатель (кончился товар,
 * изменилась цена, промокод перестал действовать): браузер принимает свежий расчёт.
 */
final class CheckoutException extends RuntimeException
{
    public function __construct(string $message, public readonly ?CartQuote $quote = null, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
