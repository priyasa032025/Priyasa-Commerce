<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        Schema::connection('priyasa')->create('priyasa_product_media', function (Blueprint $table) {
            $table->id(); $table->foreignId('product_id')->constrained('priyasa_products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('priyasa_product_variants')->nullOnDelete();
            $table->string('type', 20)->default('image'); $table->string('url'); $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0); $table->boolean('is_primary')->default(false); $table->timestamps();
            $table->index(['product_id','sort_order']);
        });
        Schema::connection('priyasa')->create('priyasa_collections', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->text('description')->nullable();
            $table->string('image_url')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::connection('priyasa')->create('priyasa_collection_product', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained('priyasa_collections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('priyasa_products')->cascadeOnDelete();
            $table->primary(['collection_id','product_id']);
        });
        Schema::connection('priyasa')->create('priyasa_wishlists', function (Blueprint $table) {
            $table->id(); $table->foreignId('customer_id')->constrained('priyasa_customers')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('priyasa_product_variants')->cascadeOnDelete(); $table->timestamps();
            $table->unique(['customer_id','variant_id']);
        });
        Schema::connection('priyasa')->create('priyasa_reviews', function (Blueprint $table) {
            $table->id(); $table->foreignId('customer_id')->constrained('priyasa_customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('priyasa_products')->cascadeOnDelete(); $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable(); $table->text('body')->nullable(); $table->string('status',20)->default('pending');
            $table->boolean('verified_purchase')->default(false); $table->timestamps(); $table->index(['product_id','status']);
        });
        Schema::connection('priyasa')->create('priyasa_shipments', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->unique()->constrained('priyasa_orders')->cascadeOnDelete();
            $table->string('provider',40); $table->string('shipment_reference')->nullable()->unique(); $table->string('awb')->nullable()->index();
            $table->string('status',40)->default('pending'); $table->string('tracking_url')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::connection('priyasa')->create('priyasa_payment_transactions', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained('priyasa_orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('priyasa_payments')->nullOnDelete(); $table->string('provider',40);
            $table->string('type',30); $table->string('provider_reference')->nullable()->index(); $table->unsignedBigInteger('amount_paise');
            $table->string('status',20); $table->json('payload')->nullable(); $table->timestamps();
            $table->index(['order_id','type','status']);
        });
        Schema::connection('priyasa')->create('priyasa_webhook_events', function (Blueprint $table) {
            $table->id(); $table->string('provider',40); $table->string('event_type',120); $table->string('event_id',191);
            $table->string('status',20)->default('received'); $table->json('payload')->nullable(); $table->text('error')->nullable(); $table->timestamp('processed_at')->nullable(); $table->timestamps();
            $table->unique(['provider','event_id']);
        });
        Schema::connection('priyasa')->create('priyasa_outbox_events', function (Blueprint $table) {
            $table->id(); $table->string('event_type',160); $table->json('payload'); $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable(); $table->unsignedInteger('attempts')->default(0); $table->text('last_error')->nullable(); $table->timestamps();
            $table->index(['published_at','created_at']);
        });
        Schema::connection('priyasa')->create('priyasa_system_settings', function (Blueprint $table) {
            $table->id(); $table->string('key')->unique(); $table->text('value')->nullable(); $table->string('type',20)->default('string');
            $table->boolean('is_public')->default(false); $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['priyasa_system_settings','priyasa_outbox_events','priyasa_webhook_events','priyasa_payment_transactions','priyasa_shipments','priyasa_reviews','priyasa_wishlists','priyasa_collection_product','priyasa_collections','priyasa_product_media'] as $table) Schema::connection('priyasa')->dropIfExists($table);
    }
};
