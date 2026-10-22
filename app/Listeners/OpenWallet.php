<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Auth\Events\Registered;

class OpenWallet
{
    public function __construct(private readonly WalletKeeper $wallets) {}

    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->wallets->open($event->user);
        }
    }
}
