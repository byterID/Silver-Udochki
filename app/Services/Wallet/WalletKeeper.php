<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Enums\WalletReason;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Единственное место, где меняется wallets.balance. Каждое изменение —
 * строка в журнале wallet_transactions с суммой и балансом после неё.
 */
class WalletKeeper
{
    public const MAX_BALANCE = 100_000_000;

    public const WELCOME_REFERENCE = 'welcome';

    /**
     * Кошелёк пользователя. Если его ещё нет (зарегистрировался, пока выкладывалась
     * новая версия, или создан командой), он открывается с приветственными деньгами.
     */
    public function walletOf(User $user): Wallet
    {
        return $user->wallet ?? $this->open($user);
    }

    /** Открывает кошелёк и начисляет приветственные деньги. Повторный вызов ничего не начисляет. */
    public function open(User $user): Wallet
    {
        // Две одновременные первые страницы не создадут два кошелька: user_id уникален
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

    /** Начисление. С $reference повторный вызов вернёт прежнюю операцию и не начислит второй раз. */
    public function credit(User $user, int $amount, WalletReason $reason, ?string $reference = null, ?string $note = null): WalletTransaction
    {
        if ($amount < 1) {
            throw new WalletException('Сумма начисления должна быть не меньше 1 ₽.');
        }

        return $this->change($user, $amount, $reason, $reference, $note);
    }

    /** Списание. Уйти в минус нельзя. С $reference повторный вызов не спишет второй раз. */
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
            // Блокировка строки: два одновременных списания не уведут баланс в минус,
            // а баланс считается от свежего значения, а не от того, что в $wallet
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $balance = $locked->balance;

            // Проверка под блокировкой, поэтому две одинаковые операции не пройдут обе
            if ($reference !== null) {
                $done = $locked->transactions()->where('reference', $reference)->first();
                if ($done !== null) {
                    return $done;
                }
            }

            $after = $locked->balance + $delta;
            if ($after < 0) {
                throw new WalletException('Недостаточно денег: на счёте '.self::money($locked->balance).', нужно '.self::money(-$delta).'.');
            }
            if ($after > self::MAX_BALANCE) {
                throw new WalletException('Слишком большой баланс: не больше '.self::money(self::MAX_BALANCE).'.');
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

    private static function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' ₽';
    }
}
