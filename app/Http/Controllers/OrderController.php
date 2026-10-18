<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CheckoutException;
use App\Services\Orders\OrderPlacer;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Оформление заказа из корзины и окно «Мои заказы». */
class OrderController extends Controller
{
    public const HISTORY_LIMIT = 30;

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $orders = Order::query()
            ->whereBelongsTo($user)
            ->with('items')
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        return OrderResource::collection($orders);
    }

    /**
     * 201 — заказ оформлен (200 — повтор попытки с тем же ключом, заказ тот же).
     * 409 — корзина разошлась с сервером: в ответе свежий расчёт, покупатель проверяет и жмёт ещё раз.
     * 422 — не хватает денег или корзина пуста.
     */
    public function store(PlaceOrderRequest $request, OrderPlacer $placer, WalletKeeper $wallets): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $session = $request->session();

        try {
            $order = $placer->place(
                $user,
                $request->quantities(),
                $session->get(CartController::SESSION_KEY),
                $request->integer('total'),
                $request->string('key')->toString(),
            );
        } catch (CheckoutException $e) {
            // Промокод перестал действовать: как и при пересчёте, забываем его
            if ($e->quote?->codeError !== null) {
                $session->forget(CartController::SESSION_KEY);
            }

            return response()->json([
                'message' => $e->getMessage(),
                'quote' => $e->quote?->toArray(),
            ], $e->quote === null ? 422 : 409);
        }

        $session->forget(CartController::SESSION_KEY);

        return response()->json([
            'order' => new OrderResource($order->load('items')),
            'balance' => $wallets->walletOf($user)->balance,
        ], $order->wasRecentlyCreated ? 201 : 200);
    }
}
