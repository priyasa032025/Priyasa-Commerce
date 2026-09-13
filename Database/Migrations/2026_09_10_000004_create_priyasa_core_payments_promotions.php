<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        Schema::connection('priyasa')->create('priyasa_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('priyasa_orders')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_order_id')->nullable()->index();
            $table->string('status', 24)->default('created');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->json('payload')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::connection('priyasa')->create('priyasa_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type', 24);
            $table->decimal('value', 12, 2);
            $table->decimal('minimum_cart_value', 12, 2)->default(0);
            $table->decimal('maximum_discount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('rules')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::connection('priyasa')->create('priyasa_coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('priyasa_coupons')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('priyasa_customers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('priyasa_orders')->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();
            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'customer_id']);
        });

        Schema::connection('priyasa')->create('priyasa_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64);
            $table->string('key', 128);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'key']);
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_idempotency_keys');
        Schema::connection('priyasa')->dropIfExists('priyasa_coupon_redemptions');
        Schema::connection('priyasa')->dropIfExists('priyasa_coupons');
        Schema::connection('priyasa')->dropIfExists('priyasa_payments');
    }
};