<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_uuid')->nullable();
            $table->string('platform'); // android, ios, web
            $table->string('fcm_token')->unique();
            $table->string('app_version')->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('platform');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
