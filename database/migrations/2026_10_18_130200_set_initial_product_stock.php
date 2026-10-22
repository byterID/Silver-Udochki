<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INITIAL_STOCK = 100;

    public function up(): void
    {
        DB::table('products')->update(['stock' => self::INITIAL_STOCK]);
    }

    public function down(): void {}
};
