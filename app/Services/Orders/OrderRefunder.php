<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\ReturnReason;
use App\Enums\WalletReason;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Stock\StockException;
use App\Services\Stock\StockKeeper;
use App\Services\Wallet\WalletException;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Возврат по заказу: заявка, товары обратно на склад и деньги на кошелёк —
 * одна транзакция. Можно вернуть часть заказа, а остальное позже.
 *
 * Блокировки в том же порядке, что у оформления (кошелёк → … → товары по id),
 * поэтому возврат и параллельный заказ того же покупателя не ловят взаимную блокировку.
 */
class OrderRefunder
{
    public function __construct(
        private readonly StockKeeper $stock,
        private readonly WalletKeeper $wallets,
    ) {}

    /**
     * @param  array<int, int>  $quantities  order_item_id => сколько штук вернуть
     * @param  string  $key  ключ идемпотентности: повтор с ним вернёт уже оформленный возврат
     *
     * @throws ReturnException
     */
    public function refund(User $user, Order $order, array $quantities, ReturnReason $reason, ?string $comment, string $key): OrderReturn
    {
        if ($order->user_id !== $user->id) {
            throw new ReturnException('Вернуть можно только свой заказ.');
        }

        $quantities = array_filter($quantities, fn (int $qty) => $qty > 0);
        if ($quantities === []) {
            throw new ReturnException('Отметьте, что возвращаете.');
        }

        $wallet = $this->wallets->walletOf($user);

        try {
            return DB::transaction(fn (): OrderReturn => $this->refundLocked($user, $wallet, $order, $quantities, $reason, $comment, $key));
        } catch (UniqueConstraintViolationException $e) {
            // Запасной путь: возврат с тем же ключом уже записан параллельным запросом
            return $this->done($order, $key) ?? throw $e;
        } catch (WalletException|StockException $e) {
            throw new ReturnException($e->getMessage(), previous: $e);
        }
    }

    /** @param array<int, int> $quantities */
    private function refundLocked(User $user, Wallet $wallet, Order $order, array $quantities, ReturnReason $reason, ?string $comment, string $key): OrderReturn
    {
        Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
        // Возвраты одного заказа идут строго по очереди: счётчики возвращённых штук не разъедутся
        Order::query()->lockForUpdate()->findOrFail($order->id);

        if (($done = $this->done($order, $key)) !== null) {
            return $done;
        }

        // Заказ и счётчики возвратов — свежие, после блокировки
        $order = Order::query()->withReturns()->findOrFail($order->id);
        $this->ensureReturnable($order, $quantities);

        [$amount, $full] = $this->amountFor($order, $quantities);

        $return = $order->returns()->create([
            'user_id' => $user->id,
            'reason' => $reason,
            'comment' => $comment,
            'amount' => $amount,
            'return_key' => $key,
        ]);

        $byProduct = [];
        foreach ($order->items as $item) {
            $qty = $quantities[$item->id] ?? 0;
            if ($qty === 0) {
                continue;
            }
            $return->items()->create(['order_item_id' => $item->id, 'quantity' => $qty]);
            if ($item->product_id !== null) {
                $byProduct[$item->product_id] = ($byProduct[$item->product_id] ?? 0) + $qty;
            }
        }

        // Товара могло уже не остаться в каталоге (восстановление каталога его стёрло):
        // тогда возвращать на склад нечего, а деньги покупатель всё равно получает
        $products = Product::query()->whereKey(array_keys($byProduct))->orderBy('id')->lockForUpdate()->get();
        foreach ($products as $product) {
            $this->stock->putBack($product, $byProduct[$product->id], $order, $user);
        }

        // Заказ, оплаченный целиком промокодом, возвращается без денег
        if ($amount > 0) {
            $this->wallets->credit($user, $amount, WalletReason::Refund, 'return:'.$return->id, 'Заказ '.$order->number());
        }

        $order->update(['status' => $full ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded]);

        return $return;
    }

    /** @param array<int, int> $quantities */
    private function ensureReturnable(Order $order, array $quantities): void
    {
        if ($order->status === OrderStatus::Refunded) {
            throw new ReturnException('Заказ уже возвращён целиком.');
        }
        if (! $order->status->isRefundable()) {
            throw new ReturnException('Этот заказ вернуть нельзя: '.mb_strtolower($order->status->label()).'.');
        }
        if (now()->greaterThan($order->returnDeadline())) {
            throw new ReturnException('Срок возврата истёк: вернуть можно в течение '.config('shop.return_days').' дней после покупки.');
        }

        $items = $order->items->keyBy('id');
        foreach ($quantities as $itemId => $qty) {
            $item = $items->get($itemId) ?? throw new ReturnException('В заказе нет такой позиции. Откройте «Мои заказы» заново.');

            $left = $item->returnableQuantity();
            if ($left === 0) {
                throw new ReturnException("«{$item->name}» уже вернули.");
            }
            if ($qty > $left) {
                throw new ReturnException("«{$item->name}»: можно вернуть не больше {$left} шт.");
            }
        }
    }

    /**
     * Сколько вернуть на кошелёк и возвращается ли теперь заказ целиком.
     *
     * Оплаченное за товары делится по стоимости строк, так что скидка по промокоду
     * ложится на каждую строку по её доле. Считаем нарастающим итогом от всех
     * возвратов заказа: несколько частичных возвратов в сумме дают ровно оплаченное,
     * округление не набегает. Доставка возвращается только вместе с последним товаром.
     * Ту же формулу считает окно возврата в orders.js.
     *
     * @param  array<int, int>  $quantities
     * @return array{int, bool}
     */
    private function amountFor(Order $order, array $quantities): array
    {
        $linesValue = 0;
        $returnedValue = 0;
        $full = true;

        foreach ($order->items as $item) {
            $returned = $item->returnedQuantity() + ($quantities[$item->id] ?? 0);
            $linesValue += $item->lineTotal();
            $returnedValue += $item->unitTotal() * $returned;
            $full = $full && $returned >= $item->quantity;
        }

        $goodsPaid = max(0, $order->total - $order->delivery_price);
        $goodsRefunded = match (true) {
            $full => $goodsPaid,
            $linesValue > 0 => intdiv($goodsPaid * $returnedValue, $linesValue),
            default => 0,
        };

        $amount = $goodsRefunded - $order->refundedAmount() + ($full ? $order->delivery_price : 0);

        return [max(0, $amount), $full];
    }

    private function done(Order $order, string $key): ?OrderReturn
    {
        return OrderReturn::query()->where('order_id', $order->id)->where('return_key', $key)->first();
    }
}
