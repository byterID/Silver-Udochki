<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Перед запуском продаж у всех товаров по 100 штук. Раньше сидер ставил
 * случайные остатки (crc32 % 50), но они ничего не значили: продаж ещё не было.
 * Откатывать нечего: прежние числа никому не нужны.
 */
return new class extends Migration
{
    private const INITIAL_STOCK = 100;

    public function up(): void
    {
        DB::table('products')->update(['stock' => self::INITIAL_STOCK]);
    }

    public function down(): void
    {
        //
    }
};
