<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\User;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Баланс и последние операции для меню под ником в шапке. */
class WalletComposer
{
    public const HISTORY_LIMIT = 10;

    public function __construct(
        private readonly WalletKeeper $wallets,
        private readonly Request $request,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();
        if (! $user instanceof User) {
            $view->with('wallet', null);

            return;
        }

        $wallet = $this->wallets->walletOf($user);

        $view->with('wallet', [
            'balance' => $wallet->balance,
            'history' => $wallet->transactions()->latest('id')->limit(self::HISTORY_LIMIT)->get(),
        ]);
    }
}
