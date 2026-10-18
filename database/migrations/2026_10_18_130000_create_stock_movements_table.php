<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Журнал движения по складу: каждое изменение остатка — строка с дельтой
 * и остатком после неё. Остаток в products.stock — итог этого журнала.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // Без внешнего ключа, как в order_items: восстановление каталога делает
            // TRUNCATE products ... CASCADE и стёрло бы журнал. Артикул и название копируются.
            $table->unsignedBigInteger('product_id');
            $table->string('sku');
            $table->string('name');
            $table->integer('delta');
            $table->unsignedInteger('stock_after');
            $table->string('reason', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'id']);
            $table->index('reason');
        });

        // unsignedInteger в PostgreSQL ничего не значит: отрицательный остаток запрещаем явно.
        // SQLite (тесты) не умеет добавлять ограничения к готовой таблице.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_non_negative CHECK (stock >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_stock_non_negative');
        }

        Schema::dropIfExists('stock_movements');
    }
};
