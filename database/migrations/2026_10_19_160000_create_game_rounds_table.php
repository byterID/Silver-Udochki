<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Раунды мини-игры. plan — полёты уток и правила, выданные при старте:
 * по нему сервер проверяет выстрелы, даже если config/game.php успели поменять.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->json('plan');
            $table->unsignedSmallInteger('shots')->default(0);
            $table->unsignedSmallInteger('hits')->default(0);
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedInteger('reward')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // Сумма наград за сутки и поиск незаконченного раунда
            $table->index(['user_id', 'status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rounds');
    }
};
