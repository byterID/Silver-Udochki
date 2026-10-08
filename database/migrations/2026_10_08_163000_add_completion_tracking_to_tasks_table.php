<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('completion_published_at')->nullable();
            $table->timestamp('notified_at')->nullable();
        });

        // Задачи из прошлых экспериментов считаем уже обработанными
        DB::table('tasks')
            ->whereNotNull('finished_at')
            ->update([
                'completion_published_at' => DB::raw('finished_at'),
                'notified_at' => DB::raw('finished_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['completion_published_at', 'notified_at']);
        });
    }
};
