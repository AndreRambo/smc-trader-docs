<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->json('features')->nullable();
            $table->integer('max_assets')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active'); // active, suspended, revoked
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider'); // hotmart, kiwify, stripe, etc.
            $table->string('provider_subscription_id')->nullable();
            $table->string('status'); // active, cancelled, past_due, unpaid
            $table->decimal('amount', 10, 2);
            $table->string('interval')->default('monthly'); // monthly, yearly
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('provider_transaction_id')->nullable();
            $table->string('status'); // completed, pending, refunded, chargeback
            $table->decimal('amount', 10, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2);
            $table->string('type'); // plan, addon, bundle
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('products');
        Schema::dropIfExists('plans');
    }
};
