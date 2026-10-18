<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ApplyPromoCodeRequest;
use App\Http\Requests\CartQuoteRequest;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Пересчёт корзины. Сама корзина живёт в localStorage, но цены, скидки
 * и промокод — только отсюда. Промокод хранится в сессии, чтобы его нельзя
 * было подставлять в каждый запрос пересчёта и подбирать перебором.
 */
class CartController extends Controller
{
    private const SESSION_KEY = 'cart.promo_code';

    public function __construct(private readonly PriceCalculator $prices) {}

    public function quote(CartQuoteRequest $request): JsonResponse
    {
        $quote = $this->prices->quote($request->quantities(), $request->session()->get(self::SESSION_KEY));

        // Промокод перестал действовать: сообщим один раз и забудем
        if ($quote->codeError !== null) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return response()->json($quote->toArray());
    }

    public function applyCode(ApplyPromoCodeRequest $request): JsonResponse
    {
        $quote = $this->prices->quote($request->quantities(), $request->string('code')->toString());

        if ($quote->code === null) {
            return response()->json([
                ...$this->prices->quote($request->quantities(), $request->session()->get(self::SESSION_KEY))->toArray(),
                'message' => $quote->codeError,
            ], 422);
        }

        $request->session()->put(self::SESSION_KEY, $quote->code->code);

        return response()->json($quote->toArray());
    }

    public function removeCode(Request $request): Response
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->noContent();
    }
}
