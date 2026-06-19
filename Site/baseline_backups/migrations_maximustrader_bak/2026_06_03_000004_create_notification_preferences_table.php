<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->json('enabled_assets')->nullable();  // ["WINFUT", "WDOFUT", ...] or null = all
            $table->json('enabled_proximities')->nullable(); // ["IMINENTE", "NA_ZONA", ...] or null = all
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('vibration_enabled')->default(true);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->integer('max_pushes_per_hour')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
