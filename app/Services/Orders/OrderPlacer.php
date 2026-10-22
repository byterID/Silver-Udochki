<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PromotionType;
use App\Enums\WalletReason;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Pricing\CartQuote;
use App\Services\Pricing\PriceCalculator;
use App\Services\Stock\StockException;
use App\Services\Stock\StockKeeper;
use App\Services\Wallet\WalletException;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// Всё в одной транзакции. Блокировки строго в порядке кошелёк → товары по id → промокод,
// иначе параллельные заказы и возвраты ловят взаимную блокировку
class OrderPlacer
{
    public function __construct(
        private readonly PriceCalculator $prices,
        private readonly StockKeeper $stock,
        private readonly WalletKeeper $wallets,
    ) {}

    /**
     * @param  array<int, int>  $quantities  product_id => количество
     * @param  int  $expectedTotal  сумма к оплате, которую видел покупатель
     * @param  string  $key  повтор с тем же ключом вернёт уже оформленный заказ
     *
     * @throws CheckoutException
     */
    public function place(User $user, array $quantities, ?string $code, int $expectedTotal, string $key): Order
    {
        $quantities = array_filter($quantities, fn (int $qty) => $qty > 0);
        if ($quantities === []) {
            throw new CheckoutException('Корзина пуста.');
        }
        ksort($quantities);

        // До транзакции: открытие кошелька начисляет подарок своей транзакцией
        $wallet = $this->wallets->walletOf($user);

        try {
            return DB::transaction(fn (): Order => $this->placeLocked($user, $wallet, $quantities, $code, $expectedTotal, $key));
        } catch (UniqueConstraintViolationException $e) {
            // Параллельный запрос с тем же ключом успел первым
            return $this->placed($user, $key) ?? throw $e;
        } catch (WalletException|StockException $e) {
            throw new CheckoutException($e->getMessage(), previous: $e);
        }
    }

    /** @param array<int, int> $quantities */
    private function placeLocked(User $user, Wallet $wallet, array $quantities, ?string $code, int $expectedTotal, string $key): Order
    {
        // Заказы покупателя идут по очереди: повтор с тем же ключом дождётся первой попытки
        Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

        if (($done = $this->placed($user, $key)) !== null) {
            return $done;
        }

        Product::query()->whereKey(array_keys($quantities))->orderBy('id')->lockForUpdate()->pluck('id');

        $code = Promotion::normalizeCode($code);
        if ($code !== null) {
            Promotion::query()->where('type', PromotionType::Fixed)->where('code', $code)->lockForUpdate()->value('id');
        }

        // Расчёт после блокировок: остатки и счётчик промокода до конца транзакции никто не поменяет
        $quote = $this->prices->quote($quantities, $code);
        $this->ensureUnchanged($quote, $code, $expectedTotal);

        $order = Order::create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'subtotal' => $quote->subtotal(),
            'items_discount' => $quote->itemsDiscount(),
            'code_discount' => $quote->codeDiscount(),
            'delivery_price' => $quote->deliveryPrice(),
            'total' => $quote->toPay(),
            'promotion_id' => $quote->code?->id,
            'promo_code' => $quote->code?->code,
            'checkout_key' => $key,
        ]);

        foreach ($quote->lines as $line) {
            $order->items()->create([
                'product_id' => $line->product->id,
                'sku' => $line->product->sku,
                'name' => $line->product->name,
                'unit_price' => $line->price->base,
                'unit_discount' => $line->price->discount(),
                'quantity' => $line->quantity,
                'promotion_id' => $line->price->promotion?->id,
            ]);

            $this->stock->sell($line->product, $line->quantity, $order, $user);
        }

        if ($order->total > 0) {
            $this->wallets->debit($user, $order->total, WalletReason::Purchase, 'order:'.$order->id, 'Заказ '.$order->number());
        }

        if ($quote->code !== null) {
            Promotion::query()->whereKey($quote->code->id)->increment('used_count');
        }

        return $order;
    }

    private function ensureUnchanged(CartQuote $quote, ?string $code, int $expectedTotal): void
    {
        if ($quote->missing !== [] || $quote->limited !== []) {
            throw new CheckoutException('Часть товаров закончилась или осталось меньше, чем в корзине. Проверьте корзину.', $quote);
        }
        if ($code !== null && $quote->code === null) {
            throw new CheckoutException($quote->codeError ?? 'Промокод больше не действует.', $quote);
        }
        if ($quote->toPay() !== $expectedTotal) {
            throw new CheckoutException('Цены изменились, пока вы выбирали. Проверьте корзину и оформите ещё раз.', $quote);
        }
    }

    private function placed(User $user, string $key): ?Order
    {
        return Order::query()->where('user_id', $user->id)->where('checkout_key', $key)->first();
    }
}
