<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_products')) {
            Schema::connection('priyasa')->table('priyasa_products', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'hsn_code')) $t->string('hsn_code', 32)->nullable()->after('tax_rate');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'gst_rate')) $t->decimal('gst_rate', 5, 2)->nullable()->after('hsn_code');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'tax_class')) $t->string('tax_class', 64)->nullable()->after('gst_rate');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'unit')) $t->string('unit', 32)->nullable()->after('tax_class');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'country_of_origin')) $t->string('country_of_origin', 2)->nullable()->after('unit');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'manufacturer')) $t->string('manufacturer')->nullable()->after('country_of_origin');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'pack_of')) $t->unsignedInteger('pack_of')->nullable()->after('manufacturer');
            });
        }

        if (Schema::connection('priyasa')->hasTable('priyasa_product_variants')) {
            Schema::connection('priyasa')->table('priyasa_product_variants', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'cost_price')) $t->decimal('cost_price', 12, 2)->nullable()->after('mrp');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'hsn_code')) $t->string('hsn_code', 32)->nullable()->after('cost_price');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'gst_rate')) $t->decimal('gst_rate', 5, 2)->nullable()->after('hsn_code');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'tax_class')) $t->string('tax_class', 64)->nullable()->after('gst_rate');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'unit')) $t->string('unit', 32)->nullable()->after('tax_class');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_variants', 'barcode_type')) $t->string('barcode_type', 32)->nullable()->after('barcode');
            });
        }

        if (Schema::connection('priyasa')->hasTable('priyasa_product_media')) {
            Schema::connection('priyasa')->table('priyasa_product_media', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_media', 'source')) $t->string('source', 32)->default('manual')->after('is_primary');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_media', 'external_id')) $t->string('external_id', 191)->nullable()->after('source');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_media', 'metadata')) $t->json('metadata')->nullable()->after('external_id');
                if (!Schema::connection('priyasa')->hasColumn('priyasa_product_media', 'source_external_id')) $t->string('source_external_id', 191)->nullable()->after('metadata');
                $t->index(['product_id','source']);
                $t->index(['source','external_id']);
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_attributes')) {
            Schema::connection('priyasa')->create('priyasa_attributes', function (Blueprint $t): void {
                $t->id();
                $t->string('name');
                $t->string('slug')->unique();
                $t->string('type', 24)->default('select');
                $t->boolean('is_variation')->default(false);
                $t->boolean('is_filterable')->default(true);
                $t->boolean('is_active')->default(true);
                $t->string('source', 32)->default('manual');
                $t->unsignedBigInteger('external_id')->nullable();
                $t->timestamps();
                $t->index(['source','external_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_attribute_options')) {
            Schema::connection('priyasa')->create('priyasa_attribute_options', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('attribute_id')->constrained('priyasa_attributes')->cascadeOnDelete();
                $t->string('label');
                $t->string('slug');
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['attribute_id','slug']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_attribute_options');
        Schema::connection('priyasa')->dropIfExists('priyasa_attributes');
    }
};
