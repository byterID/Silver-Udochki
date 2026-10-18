<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Каркас заказов: пока только таблицы, оформления ещё нет.
 * Цены, скидки и названия товаров копируются в заказ на момент покупки,
 * чтобы позже изменения каталога и акций не меняли историю.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('new');
            // Сумма по базовым ценам, скидка по акциям на товары, скидка по промокоду
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('items_discount')->default(0);
            $table->unsignedInteger('code_discount')->default(0);
            $table->unsignedInteger('delivery_price')->default(0);
            $table->unsignedInteger('total');
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('promo_code', 32)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Без внешнего ключа: восстановление каталога делает TRUNCATE products ... CASCADE
            // и стёрло бы строки заказов. Название и артикул копируются ниже.
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('sku');
            $table->string('name');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('unit_discount')->default(0);
            $table->unsignedInteger('quantity');
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
