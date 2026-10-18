<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Уже зарегистрированным пользователям — кошелёк с приветственными 20 000 ₽.
 * Новым их начисляет слушатель Registered. Сумма зашита константой, а не берётся
 * из config/shop.php: миграция должна давать один результат, когда бы её ни запустили.
 */
return new class extends Migration
{
    private const WELCOME_BALANCE = 20_000;

    public function up(): void
    {
        DB::table('users')
            ->whereNotExists(fn ($query) => $query->from('wallets')->whereColumn('wallets.user_id', 'users.id'))
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                $now = now();

                foreach ($users as $user) {
                    DB::transaction(function () use ($user, $now) {
                        $walletId = DB::table('wallets')->insertGetId([
                            'user_id' => $user->id,
                            'balance' => self::WELCOME_BALANCE,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        DB::table('wallet_transactions')->insert([
                            'wallet_id' => $walletId,
                            'amount' => self::WELCOME_BALANCE,
                            'balance_after' => self::WELCOME_BALANCE,
                            'reason' => 'welcome',
                            'reference' => 'welcome',
                            'created_at' => $now,
                        ]);
                    });
                }
            });
    }

    public function down(): void
    {
        // Кошельки удаляет откат предыдущей миграции вместе с таблицами
    }
};
