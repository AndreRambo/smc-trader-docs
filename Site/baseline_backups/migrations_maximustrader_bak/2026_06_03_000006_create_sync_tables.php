<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assets for sync
        if (!Schema::hasTable('sync_assets')) {
            Schema::create('sync_assets', function (Blueprint $table) {
                $table->id();
                $table->string('ticker')->unique();
                $table->string('name')->nullable();
                $table->string('code')->nullable();
                $table->timestamps();
            });
        }

        // Candles
        if (!Schema::hasTable('sync_candles')) {
            Schema::create('sync_candles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sync_asset_id')->constrained()->cascadeOnDelete();
                $table->string('ticker');
                $table->string('timeframe');
                $table->dateTime('timestamp');
                $table->decimal('open', 18, 8)->nullable();
                $table->decimal('high', 18, 8)->nullable();
                $table->decimal('low', 18, 8)->nullable();
                $table->decimal('close', 18, 8)->nullable();
                $table->decimal('ema20', 18, 8)->nullable();
                $table->decimal('ema200', 18, 8)->nullable();
                $table->decimal('rsi', 10, 4)->nullable();
                $table->decimal('volume', 18, 2)->nullable();
                $table->decimal('atr', 18, 8)->nullable();
                $table->timestamps();
                $table->unique(['sync_asset_id', 'timeframe', 'timestamp'], 'uq_sync_candle');
                $table->index('ticker');
                $table->index('timeframe');
            });
        }

        // SMC Zones
        if (!Schema::hasTable('sync_zones')) {
            Schema::create('sync_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sync_asset_id')->constrained()->cascadeOnDelete();
                $table->string('ticker');
                $table->string('timeframe')->default('5min');
                $table->string('zone_type')->nullable();
                $table->string('type')->nullable();
                $table->decimal('price_top', 18, 8)->nullable();
                $table->decimal('price_bottom', 18, 8)->nullable();
                $table->decimal('top', 18, 8)->nullable();
                $table->decimal('bottom', 18, 8)->nullable();
                $table->dateTime('created_at_candle')->nullable();
                $table->string('status')->default('active');
                $table->dateTime('mitigated_at')->nullable();
                $table->timestamps();
                $table->index('ticker');
                $table->index('status');
            });
        }

        // Studies
        if (!Schema::hasTable('sync_studies')) {
            Schema::create('sync_studies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sync_asset_id')->constrained()->cascadeOnDelete();
                $table->string('ticker');
                $table->string('timeframe')->default('5min');
                $table->json('indicators')->nullable();
                $table->json('projections')->nullable();
                $table->json('zones')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index('ticker');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_studies');
        Schema::dropIfExists('sync_zones');
        Schema::dropIfExists('sync_candles');
        Schema::dropIfExists('sync_assets');
    }
};
