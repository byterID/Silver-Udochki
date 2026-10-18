<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Ключ идемпотентности оформления: браузер присылает один и тот же ключ,
 * пока повторяет попытку (обрыв сети, двойной клик), и второй заказ не появится.
 * Колонка nullable: старая версия приложения заказов не создаёт и о ней не знает.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_key', 64)->nullable()->after('promo_code');

            $table->unique(['user_id', 'checkout_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'checkout_key']);
            $table->dropColumn('checkout_key');
        });
    }
};
