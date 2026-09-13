<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        Schema::connection('priyasa')->create('priyasa_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('priyasa_customers')->cascadeOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->timestamp('last_added_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('priyasa')->create('priyasa_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('priyasa_carts')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('priyasa_product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
            $table->unique(['cart_id', 'variant_id']);
        });

        Schema::connection('priyasa')->create('priyasa_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('priyasa_customers')->restrictOnDelete();
            $table->foreignId('shipping_address_id')->nullable()->constrained('priyasa_addresses')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status', 32)->default('pending_payment');
            $table->string('currency', 3)->default('INR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('payment_status', 24)->default('pending');
            $table->string('payment_method', 32)->nullable();
            $table->string('source', 32)->default('website');
            $table->json('metadata')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::connection('priyasa')->create('priyasa_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('priyasa_orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('priyasa_product_variants')->restrictOnDelete();
            $table->string('sku');
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::connection('priyasa')->create('priyasa_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('priyasa_orders')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('actor_type', 32)->nullable();
            $table->string('actor_id')->nullable();
            $table->string('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_order_status_history');
        Schema::connection('priyasa')->dropIfExists('priyasa_order_items');
        Schema::connection('priyasa')->dropIfExists('priyasa_orders');
        Schema::connection('priyasa')->dropIfExists('priyasa_cart_items');
        Schema::connection('priyasa')->dropIfExists('priyasa_carts');
    }
};