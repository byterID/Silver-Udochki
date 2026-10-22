<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // Без FK по той же причине, что в order_items
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

        // unsigned в PostgreSQL не работает. SQLite не умеет добавлять CHECK к готовой таблице
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
