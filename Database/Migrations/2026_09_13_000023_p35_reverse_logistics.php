<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void {
        if (!Schema::connection('priyasa')->hasTable('priyasa_reverse_shipments')) {
            Schema::connection('priyasa')->create('priyasa_reverse_shipments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('return_id')->nullable()->index();
                $t->unsignedBigInteger('order_id')->index();
                $t->string('provider', 40)->default('shiprocket');
                $t->string('provider_shipment_id', 120)->nullable()->index();
                $t->string('awb', 120)->nullable()->index();
                $t->string('status', 40)->default('requested')->index();
                $t->string('pickup_status', 40)->nullable();
                $t->string('tracking_url', 500)->nullable();
                $t->text('failure_reason')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamp('picked_up_at')->nullable();
                $t->timestamp('received_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->timestamps();
                $t->unique(['provider','provider_shipment_id'], 'pc_reverse_provider_shipment_uq');
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_return_qc')) {
            Schema::connection('priyasa')->create('priyasa_return_qc', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('return_id')->index();
                $t->string('status', 30)->default('pending')->index();
                $t->boolean('restock')->default(false);
                $t->boolean('damaged')->default(false);
                $t->text('notes')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index(['return_id','status']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_ndr_cases')) {
            Schema::connection('priyasa')->create('priyasa_ndr_cases', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('shipment_id')->index();
                $t->string('reason', 120)->nullable();
                $t->string('status', 30)->default('open')->index();
                $t->unsignedInteger('attempts')->default(1);
                $t->timestamp('next_action_at')->nullable();
                $t->text('customer_action')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index(['shipment_id','status']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_exchange_orders')) {
            Schema::connection('priyasa')->create('priyasa_exchange_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('return_id')->index();
                $t->unsignedBigInteger('order_id')->index();
                $t->unsignedBigInteger('replacement_order_id')->nullable()->index();
                $t->string('status', 30)->default('requested')->index();
                $t->json('metadata')->nullable();
                $t->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::connection('priyasa')->dropIfExists('priyasa_exchange_orders');
        Schema::connection('priyasa')->dropIfExists('priyasa_ndr_cases');
        Schema::connection('priyasa')->dropIfExists('priyasa_return_qc');
        Schema::connection('priyasa')->dropIfExists('priyasa_reverse_shipments');
    }
};
