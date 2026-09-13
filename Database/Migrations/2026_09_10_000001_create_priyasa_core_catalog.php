<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        Schema::connection('priyasa')->create('priyasa_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('priyasa_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['parent_id', 'is_active']);
        });

        Schema::connection('priyasa')->create('priyasa_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('priyasa_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('mrp', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('status')->default('draft');
            $table->string('brand')->nullable();
            $table->json('attributes')->nullable();
            $table->json('media')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
        });

        Schema::connection('priyasa')->create('priyasa_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('priyasa_products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('mrp', 12, 2)->nullable();
            $table->decimal('weight_grams', 10, 2)->nullable();
            $table->string('image_url')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_id', 'is_active']);
        });

        Schema::connection('priyasa')->create('priyasa_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->unique()->constrained('priyasa_product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(3);
            $table->timestamps();
        });

        Schema::connection('priyasa')->create('priyasa_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('priyasa_product_variants')->cascadeOnDelete();
            $table->string('type', 32);
            $table->integer('quantity');
            $table->unsignedInteger('balance_after')->default(0);
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['variant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id'], 'pc_inventory_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_inventory_movements');
        Schema::connection('priyasa')->dropIfExists('priyasa_inventory');
        Schema::connection('priyasa')->dropIfExists('priyasa_product_variants');
        Schema::connection('priyasa')->dropIfExists('priyasa_products');
        Schema::connection('priyasa')->dropIfExists('priyasa_categories');
    }
};