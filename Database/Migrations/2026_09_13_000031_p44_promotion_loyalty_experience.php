<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_coupons')) {
            Schema::connection('priyasa')->table('priyasa_coupons', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_coupons', 'prepaid_only')) $t->boolean('prepaid_only')->default(false);
                if (!Schema::connection('priyasa')->hasColumn('priyasa_coupons', 'customer_segments')) $t->json('customer_segments')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_coupons', 'description')) $t->string('description', 500)->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_coupons', 'display_label')) $t->string('display_label', 160)->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_coupons', 'terms')) $t->json('terms')->nullable();
            });
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_promotion_campaigns')) {
            Schema::connection('priyasa')->table('priyasa_promotion_campaigns', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_promotion_campaigns', 'prepaid_only')) $t->boolean('prepaid_only')->default(false);
                if (!Schema::connection('priyasa')->hasColumn('priyasa_promotion_campaigns', 'customer_segments')) $t->json('customer_segments')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_promotion_campaigns', 'description')) $t->string('description', 500)->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_promotion_campaigns', 'display_label')) $t->string('display_label', 160)->nullable();
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_promotion_events')) {
            Schema::connection('priyasa')->create('priyasa_promotion_events', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('customer_id')->nullable()->constrained('priyasa_customers')->nullOnDelete();
                $t->foreignId('coupon_id')->nullable()->constrained('priyasa_coupons')->nullOnDelete();
                $t->foreignId('campaign_id')->nullable()->constrained('priyasa_promotion_campaigns')->nullOnDelete();
                $t->foreignId('order_id')->nullable()->constrained('priyasa_orders')->nullOnDelete();
                $t->string('event_type', 40);
                $t->decimal('discount_amount', 12, 2)->default(0);
                $t->string('code', 64)->nullable();
                $t->string('idempotency_key', 160)->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index(['customer_id','event_type','created_at'], 'pc_promo_evt_customer_type_time_idx');
                $t->index(['coupon_id','event_type','created_at'], 'pc_promo_evt_coupon_type_time_idx');
                $t->unique(['event_type','idempotency_key'], 'pc_promo_evt_type_key_uq');
            });
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_loyalty_accounts')) {
            Schema::connection('priyasa')->table('priyasa_loyalty_accounts', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_loyalty_accounts', 'lifetime_points')) $t->unsignedBigInteger('lifetime_points')->default(0);
                if (!Schema::connection('priyasa')->hasColumn('priyasa_loyalty_accounts', 'points_expiring_soon')) $t->unsignedBigInteger('points_expiring_soon')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_promotion_events');
        foreach (['prepaid_only','customer_segments','description','display_label','terms'] as $c) {
            if (Schema::connection('priyasa')->hasTable('priyasa_coupons') && Schema::connection('priyasa')->hasColumn('priyasa_coupons', $c)) Schema::connection('priyasa')->table('priyasa_coupons', fn(Blueprint $t) => $t->dropColumn($c));
        }
        foreach (['prepaid_only','customer_segments','description','display_label'] as $c) {
            if (Schema::connection('priyasa')->hasTable('priyasa_promotion_campaigns') && Schema::connection('priyasa')->hasColumn('priyasa_promotion_campaigns', $c)) Schema::connection('priyasa')->table('priyasa_promotion_campaigns', fn(Blueprint $t) => $t->dropColumn($c));
        }
    }
};
