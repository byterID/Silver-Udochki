<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Виртуальный кошелёк: баланс в рублях и журнал операций по нему.
 * Отдельные таблицы, а не колонка в users: деплой-миграции только добавляют таблицы,
 * а старая версия приложения продолжает работать с users как раньше.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 20);
            // Ключ идемпотентности («welcome», позже «order:15»): повтор операции не спишет дважды
            $table->string('reference', 64)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['wallet_id', 'reference']);
            $table->index(['wallet_id', 'id']);
        });

        // Как и у остатков: unsignedInteger в PostgreSQL ничего не значит
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_non_negative CHECK (balance >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
