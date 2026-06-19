<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scanner_alert_id')->nullable()->constrained()->nullOnDelete();
            $table->string('alert_id')->unique();
            $table->string('symbol');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('direction')->nullable(); // ALTISTA, BAIXISTA
            $table->string('proximity')->nullable(); // OBSERVANDO, PROXIMO, IMINENTE, NA_ZONA
            $table->string('severity')->nullable(); // LOW, MEDIUM, HIGH, CRITICAL
            $table->string('status')->default('active'); // active, expired, invalidated, completed
            $table->decimal('entrada', 18, 6)->nullable();
            $table->decimal('stop', 18, 6)->nullable();
            $table->decimal('tp1', 18, 6)->nullable();
            $table->decimal('tp2', 18, 6)->nullable();
            $table->decimal('tp3', 18, 6)->nullable();
            $table->decimal('current_price', 18, 6)->nullable();
            $table->decimal('rr_tp1', 10, 2)->nullable();
            $table->decimal('distance_to_entry_pts', 18, 6)->nullable();
            $table->decimal('distance_to_entry_atr', 18, 6)->nullable();
            $table->text('message')->nullable();
            $table->integer('opened_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('symbol');
            $table->index('direction');
            $table->index('proximity');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
