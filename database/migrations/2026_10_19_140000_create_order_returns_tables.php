<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Возвраты по заказам: заявка покупателя и вернувшиеся строки заказа.
 * Отдельные таблицы, а не колонки в orders/order_items: старая версия
 * приложения о возвратах не знает и продолжает работать как раньше.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 30);
            $table->text('comment')->nullable();
            // Сколько вернули на кошелёк этим возвратом (доставка — только в последнем, полном)
            $table->unsignedInteger('amount');
            // Ключ идемпотентности: повтор отправки формы не оформит второй возврат
            $table->string('return_key', 64);
            $table->timestamps();

            $table->unique(['order_id', 'return_key']);
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};
