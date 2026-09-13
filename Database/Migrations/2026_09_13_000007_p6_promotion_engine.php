<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_promotion_campaigns')) {
            Schema::connection('priyasa')->create('priyasa_promotion_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('type', 32)->default('automatic');
                $table->string('discount_type', 24)->default('percentage');
                $table->decimal('discount_value', 12, 2)->default(0);
                $table->decimal('minimum_cart_value', 12, 2)->default(0);
                $table->decimal('maximum_discount', 12, 2)->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->unsignedInteger('per_customer_limit')->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->boolean('stackable')->default(false);
                $table->boolean('first_order_only')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('rules')->nullable();
                $table->timestamps();
                $table->index(['is_active','starts_at','ends_at','priority'], 'pc_promo_active_window_priority_idx');
            });
        }

        // MySQL limits identifier names to 64 characters. The Laravel-generated
        // name for the four-column index is too long for this table name.
        // Keep the index name explicit and short, and make this migration safe
        // when the table was already created before a previous failed attempt.
        if (Schema::connection('priyasa')->hasTable('priyasa_promotion_campaigns')) {
            $hasIndex = DB::connection('priyasa')->selectOne(
                "SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'priyasa_promotion_campaigns'
                   AND index_name = 'pc_promo_active_window_priority_idx'
                 LIMIT 1"
            );
            if (!$hasIndex) {
                Schema::connection('priyasa')->table('priyasa_promotion_campaigns', function (Blueprint $table) {
                    $table->index(['is_active','starts_at','ends_at','priority'], 'pc_promo_active_window_priority_idx');
                });
            }
        }

        foreach (['stackable','auto_apply','first_order_only','priority'] as $column) {
            if (!Schema::connection('priyasa')->hasColumn('priyasa_coupons', $column)) {
                Schema::connection('priyasa')->table('priyasa_coupons', function (Blueprint $table) use ($column) {
                    if ($column === 'stackable' || $column === 'auto_apply' || $column === 'first_order_only') $table->boolean($column)->default(false);
                    else $table->unsignedInteger($column)->default(100);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_promotion_campaigns')) {
            Schema::connection('priyasa')->dropIfExists('priyasa_promotion_campaigns');
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_coupons')) {
            foreach (['stackable','auto_apply','first_order_only','priority'] as $column) {
                if (Schema::connection('priyasa')->hasColumn('priyasa_coupons', $column)) {
                    Schema::connection('priyasa')->table('priyasa_coupons', fn(Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
