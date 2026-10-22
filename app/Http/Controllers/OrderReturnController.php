<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReturnOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderRefunder;
use App\Services\Orders\ReturnException;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Http\JsonResponse;

class OrderReturnController extends Controller
{
    // 200 вместо 201 — повтор с тем же ключом вернул уже оформленный возврат
    public function store(ReturnOrderRequest $request, Order $order, OrderRefunder $refunder, WalletKeeper $wallets): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $return = $refunder->refund(
                $user,
                $order,
                $request->quantities(),
                $request->reason() ?? throw new ReturnException('Выберите причину возврата.'),
                $request->comment(),
                $request->string('key')->toString(),
            );
        } catch (ReturnException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'order' => new OrderResource(Order::query()->withReturns()->findOrFail($order->id)),
            'refund' => $return->amount,
            'balance' => $wallets->walletOf($user)->balance,
        ], $return->wasRecentlyCreated ? 201 : 200);
    }
}
