<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Elliott waves (one row per wave leg)
        if (!Schema::hasTable('sync_elliott_waves')) {
            Schema::create('sync_elliott_waves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sync_asset_id')->constrained()->cascadeOnDelete();
                $table->string('ticker');
                $table->string('timeframe')->default('5min');
                $table->string('wave_label')->nullable();      // W1..W5 / WA..WC
                $table->string('direction')->nullable();       // up / down
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->decimal('start_price', 18, 8)->nullable();
                $table->decimal('end_price', 18, 8)->nullable();
                $table->timestamps();
                $table->index(['ticker', 'timeframe']);
            });
        }

        // Wyckoff phases / ranges (consolidation zones over time)
        if (!Schema::hasTable('sync_wyckoff_phases')) {
            Schema::create('sync_wyckoff_phases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sync_asset_id')->constrained()->cascadeOnDelete();
                $table->string('ticker');
                $table->string('timeframe')->default('5min');
                $table->string('phase')->nullable();           // ACUMULACAO / DISTRIBUICAO / ...
                $table->decimal('range_high', 18, 8)->nullable();
                $table->decimal('range_low', 18, 8)->nullable();
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->timestamps();
                $table->index(['ticker', 'timeframe']);
            });
        }

        // Wyckoff events (point markers: SPRING, UPTHRUST, SOS, ...)
        if (!Schema::hasTable('sync_wyckoff_events')) {
            Schema::create('sync_wyckoff_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sync_asset_id')->constrained()->cascadeOnDelete();
                $table->string('ticker');
                $table->string('timeframe')->default('5min');
                $table->string('event_type')->nullable();
                $table->string('direction')->nullable();
                $table->decimal('price', 18, 8)->nullable();
                $table->dateTime('event_time')->nullable();
                $table->timestamps();
                $table->index(['ticker', 'timeframe']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_wyckoff_events');
        Schema::dropIfExists('sync_wyckoff_phases');
        Schema::dropIfExists('sync_elliott_waves');
    }
};
