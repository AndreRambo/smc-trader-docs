<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // rsi, macd, ema, bollinger, volume_profile
            $table->json('config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol');
            $table->string('timeframe');
            $table->string('alert_type'); // price, zone, study
            $table->string('condition')->nullable(); // above, below, touched, mitigated
            $table->decimal('value', 18, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->json('value');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('indicators');
        Schema::dropIfExists('configurations');
    }
};
