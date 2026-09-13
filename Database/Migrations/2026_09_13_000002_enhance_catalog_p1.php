<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_products') && !Schema::connection('priyasa')->hasColumn('priyasa_products', 'brand_slug')) {
            Schema::connection('priyasa')->table('priyasa_products', function (Blueprint $table): void {
                $table->string('brand_slug', 191)->nullable()->after('brand');
            });
        }

        if (Schema::connection('priyasa')->hasTable('priyasa_product_variants')) {
            Schema::connection('priyasa')->table('priyasa_product_variants', function (Blueprint $table): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'gst_rate')) $table->decimal('gst_rate', 5, 2)->nullable()->after('mrp');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'hsn_code')) $table->string('hsn_code', 32)->nullable()->after('gst_rate');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'tax_class')) $table->string('tax_class', 64)->nullable()->after('hsn_code');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'unit')) $table->string('unit', 32)->nullable()->after('tax_class');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'barcode_type')) $table->string('barcode_type', 32)->nullable()->after('barcode');
            });
        }

        if (Schema::connection('priyasa')->hasTable('priyasa_attributes') && Schema::connection('priyasa')->hasTable('priyasa_products') && !Schema::connection('priyasa')->hasTable('priyasa_product_attributes')) {
            Schema::connection('priyasa')->create('priyasa_product_attributes', function (Blueprint $table): void {
                $table->foreignId('product_id')->constrained('priyasa_products')->cascadeOnDelete();
                $table->foreignId('attribute_id')->constrained('priyasa_attributes')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->primary(['product_id', 'attribute_id']);
            });
        }

        if (Schema::connection('priyasa')->hasTable('priyasa_attribute_options') && Schema::connection('priyasa')->hasTable('priyasa_product_variants') && !Schema::connection('priyasa')->hasTable('priyasa_variant_attribute_options')) {
            Schema::connection('priyasa')->create('priyasa_variant_attribute_options', function (Blueprint $table): void {
                $table->foreignId('variant_id')->constrained('priyasa_product_variants')->cascadeOnDelete();
                $table->foreignId('attribute_option_id')->constrained('priyasa_attribute_options')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['variant_id', 'attribute_option_id']);
                $table->index('attribute_option_id');
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_price_change_logs')) {
            Schema::connection('priyasa')->create('priyasa_price_change_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->string('field', 32);
                $table->decimal('old_value', 12, 2)->nullable();
                $table->decimal('new_value', 12, 2)->nullable();
                $table->string('operation', 32)->nullable();
                $table->decimal('operation_value', 12, 2)->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('source', 32)->default('admin');
                $table->string('reason')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'created_at']);
                $table->index(['variant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_price_change_logs');
        Schema::connection('priyasa')->dropIfExists('priyasa_variant_attribute_options');
        Schema::connection('priyasa')->dropIfExists('priyasa_product_attributes');
        if (Schema::connection('priyasa')->hasTable('priyasa_products') && Schema::connection('priyasa')->hasColumn('priyasa_products', 'brand_slug')) {
            Schema::connection('priyasa')->table('priyasa_products', fn (Blueprint $table) => $table->dropColumn('brand_slug'));
        }
    }
};
