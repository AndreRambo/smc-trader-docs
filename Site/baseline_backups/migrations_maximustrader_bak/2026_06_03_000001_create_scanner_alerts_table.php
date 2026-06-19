<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_id')->unique();
            $table->string('signal_id')->nullable();
            $table->string('plan_id')->nullable();
            $table->string('symbol');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('direction')->nullable(); // ALTISTA, BAIXISTA
            $table->string('proximity')->nullable(); // OBSERVANDO, PROXIMO, IMINENTE, NA_ZONA
            $table->string('severity')->nullable(); // LOW, MEDIUM, HIGH, CRITICAL
            $table->decimal('entrada', 18, 6)->nullable();
            $table->decimal('stop', 18, 6)->nullable();
            $table->decimal('tp1', 18, 6)->nullable();
            $table->decimal('tp2', 18, 6)->nullable();
            $table->decimal('tp3', 18, 6)->nullable();
            $table->decimal('current_price', 18, 6)->nullable();
            $table->decimal('distance_to_entry_pts', 18, 6)->nullable();
            $table->decimal('distance_to_entry_atr', 18, 6)->nullable();
            $table->dateTime('feed_candle_time')->nullable();
            $table->dateTime('base_candle_time')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('received_at');
            $table->timestamps();

            $table->index('symbol');
            $table->index('direction');
            $table->index('proximity');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_alerts');
    }
};
