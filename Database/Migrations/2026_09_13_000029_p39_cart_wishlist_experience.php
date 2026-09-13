<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_carts')) {
            Schema::connection('priyasa')->table('priyasa_carts', function (Blueprint $table): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_carts', 'cart_token')) {
                    $table->string('cart_token', 80)->nullable()->unique()->after('id');
                }
                if (!Schema::connection('priyasa')->hasColumn('priyasa_carts', 'merged_at')) {
                    $table->timestamp('merged_at')->nullable();
                }
            });
            // Existing customer_id is unique; nullable guest carts can coexist.
            if (Schema::connection('priyasa')->hasColumn('priyasa_carts', 'customer_id')) {
                try { Schema::connection('priyasa')->table('priyasa_carts', fn (Blueprint $t) => $t->foreignId('customer_id')->nullable()->change()); } catch (Throwable) {}
            }
        }

        if (Schema::connection('priyasa')->hasTable('priyasa_cart_items')) {
            Schema::connection('priyasa')->table('priyasa_cart_items', function (Blueprint $table): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_cart_items', 'price_snapshot')) $table->decimal('price_snapshot', 12, 2)->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_cart_items', 'added_at')) $table->timestamp('added_at')->nullable();
            });
        }

        if (!Schema::connection('priyasa')->hasTable('priyasa_saved_cart_items')) {
            Schema::connection('priyasa')->create('priyasa_saved_cart_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->nullable()->constrained('priyasa_customers')->cascadeOnDelete();
                $table->string('session_id', 120)->nullable();
                $table->foreignId('variant_id')->constrained('priyasa_product_variants')->restrictOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('price_snapshot', 12, 2)->nullable();
                $table->timestamps();
                $table->unique(['customer_id', 'variant_id']);
                $table->index(['session_id', 'variant_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_saved_cart_items');
        if (Schema::connection('priyasa')->hasTable('priyasa_cart_items')) Schema::connection('priyasa')->table('priyasa_cart_items', function (Blueprint $t): void { foreach (['price_snapshot','added_at'] as $c) if (Schema::connection('priyasa')->hasColumn('priyasa_cart_items',$c)) $t->dropColumn($c); });
        if (Schema::connection('priyasa')->hasTable('priyasa_carts')) Schema::connection('priyasa')->table('priyasa_carts', function (Blueprint $t): void { foreach (['cart_token','merged_at'] as $c) if (Schema::connection('priyasa')->hasColumn('priyasa_carts',$c)) $t->dropColumn($c); });
    }
};
