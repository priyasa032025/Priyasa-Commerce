<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_wishlists')) {
            Schema::connection('priyasa')->create('priyasa_wishlists', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('customer_id')->nullable()->constrained('priyasa_customers')->cascadeOnDelete();
                $t->string('session_id', 120)->nullable();
                $t->timestamps();
                $t->index(['customer_id']);
                $t->index(['session_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_wishlist_items')) {
            Schema::connection('priyasa')->create('priyasa_wishlist_items', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('wishlist_id')->constrained('priyasa_wishlists')->cascadeOnDelete();
                $t->foreignId('product_id')->constrained('priyasa_products')->cascadeOnDelete();
                $t->foreignId('variant_id')->nullable()->constrained('priyasa_product_variants')->nullOnDelete();
                $t->decimal('price_snapshot', 12, 2)->nullable();
                $t->timestamp('price_seen_at')->nullable();
                $t->timestamp('added_at')->nullable();
                $t->timestamps();
                $t->unique(['wishlist_id','product_id','variant_id'], 'p43_wishlist_item_unique');
                $t->index(['product_id']);
                $t->index(['variant_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_engagement_alerts')) {
            Schema::connection('priyasa')->create('priyasa_engagement_alerts', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('customer_id')->nullable()->constrained('priyasa_customers')->cascadeOnDelete();
                $t->string('session_id', 120)->nullable();
                $t->foreignId('product_id')->constrained('priyasa_products')->cascadeOnDelete();
                $t->string('type', 40);
                $t->boolean('enabled')->default(true);
                $t->timestamp('last_triggered_at')->nullable();
                $t->timestamps();
                $t->unique(['customer_id','product_id','type'], 'p43_alert_customer_product_type');
                $t->index(['session_id','product_id','type'], 'pc_engage_session_product_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_engagement_alerts');
        Schema::connection('priyasa')->dropIfExists('priyasa_wishlist_items');
        Schema::connection('priyasa')->dropIfExists('priyasa_wishlists');
    }
};
