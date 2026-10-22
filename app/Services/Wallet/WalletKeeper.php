<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Enums\WalletReason;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

// Единственное место, где меняется wallets.balance: каждое изменение пишется в wallet_transactions
class WalletKeeper
{
    public const MAX_BALANCE = 100_000_000;

    public const WELCOME_REFERENCE = 'welcome';

    // Кошелька может не быть (пользователь создан командой или во время выкладки): тогда открываем
    public function walletOf(User $user): Wallet
    {
        return $user->wallet ?? $this->open($user);
    }

    public function open(User $user): Wallet
    {
        // insertOrIgnore по уникальному user_id: параллельные первые запросы не создадут два кошелька
        Wallet::query()->insertOrIgnore([
            'user_id' => $user->id,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $user->setRelation('wallet', $wallet);

        $bonus = (int) config('shop.welcome_balance');
        if ($bonus > 0) {
            $this->credit($user, $bonus, WalletReason::Welcome, self::WELCOME_REFERENCE);
        }

        return $wallet;
    }

    // С $reference операция идемпотентна: повтор вернёт прежнюю запись
    public function credit(User $user, int $amount, WalletReason $reason, ?string $reference = null, ?string $note = null): WalletTransaction
    {
        if ($amount < 1) {
            throw new WalletException('Сумма начисления должна быть не меньше 1 ₽.');
        }

        return $this->change($user, $amount, $reason, $reference, $note);
    }

    public function debit(User $user, int $amount, WalletReason $reason, ?string $reference = null, ?string $note = null): WalletTransaction
    {
        if ($amount < 1) {
            throw new WalletException('Сумма списания должна быть не меньше 1 ₽.');
        }

        return $this->change($user, -$amount, $reason, $reference, $note);
    }

    private function change(User $user, int $delta, WalletReason $reason, ?string $reference, ?string $note): WalletTransaction
    {
        $wallet = $this->walletOf($user);
        $balance = $wallet->balance;

        $transaction = DB::transaction(function () use ($wallet, $delta, $reason, $reference, $note, &$balance): WalletTransaction {
            // Баланс и повтор по $reference проверяются под блокировкой строки кошелька
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $balance = $locked->balance;

            if ($reference !== null) {
                $done = $locked->transactions()->where('reference', $reference)->first();
                if ($done !== null) {
                    return $done;
                }
            }

            $after = $locked->balance + $delta;
            if ($after < 0) {
                throw new WalletException('Недостаточно денег: на счёте '.Money::format($locked->balance).', нужно '.Money::format(-$delta).'.');
            }
            if ($after > self::MAX_BALANCE) {
                throw new WalletException('Слишком большой баланс: не больше '.Money::format(self::MAX_BALANCE).'.');
            }

            $locked->update(['balance' => $after]);
            $balance = $after;

            return $locked->transactions()->create([
                'amount' => $delta,
                'balance_after' => $after,
                'reason' => $reason,
                'reference' => $reference,
                'note' => $note,
            ]);
        });

        // Вызывающий код продолжает работать со своим экземпляром кошелька
        $wallet->balance = $balance;
        $wallet->syncOriginalAttribute('balance');

        return $transaction;
    }
}
