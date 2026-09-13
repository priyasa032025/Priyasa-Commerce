<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void {
        Schema::connection('priyasa')->table('priyasa_payments', function (Blueprint $t) {
            $t->string('method', 32)->nullable()->after('provider');
            $t->decimal('refunded_amount', 12, 2)->default(0)->after('amount');
            $t->timestamp('failed_at')->nullable()->after('captured_at');
            $t->timestamp('refunded_at')->nullable()->after('failed_at');
            $t->index(['provider_payment_id','status']);
        });
        Schema::connection('priyasa')->table('priyasa_payment_transactions', function (Blueprint $t) {
            $t->string('provider_reference', 191)->nullable()->change();
            $t->string('type', 32)->index()->change();
            $t->unsignedBigInteger('refund_id')->nullable()->index();
        });
        Schema::connection('priyasa')->table('priyasa_shipments', function (Blueprint $t) {
            $t->string('provider_shipment_id', 64)->nullable()->index();
            $t->string('courier_name', 100)->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
        });
        if (!Schema::connection('priyasa')->hasColumn('priyasa_refunds', 'currency')) Schema::connection('priyasa')->table('priyasa_refunds', fn(Blueprint $t) => $t->string('currency', 3)->default('INR'));
        if (!Schema::connection('priyasa')->hasColumn('priyasa_refunds', 'provider_refund_id')) Schema::connection('priyasa')->table('priyasa_refunds', fn(Blueprint $t) => $t->string('provider_refund_id',191)->nullable()->index());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_refunds', 'payload')) Schema::connection('priyasa')->table('priyasa_refunds', fn(Blueprint $t) => $t->json('payload')->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_refunds', 'processed_at')) Schema::connection('priyasa')->table('priyasa_refunds', fn(Blueprint $t) => $t->timestamp('processed_at')->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_refunds', 'return_id')) Schema::connection('priyasa')->table('priyasa_refunds', fn(Blueprint $t) => $t->foreignId('return_id')->nullable()->after('payment_id')->constrained('priyasa_returns')->nullOnDelete());
        Schema::connection('priyasa')->create('priyasa_shipment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained('priyasa_shipments')->cascadeOnDelete();
            $t->string('external_event_id',191)->nullable();
            $t->string('status',64);
            $t->string('location',191)->nullable();
            $t->timestamp('occurred_at')->nullable();
            $t->json('payload')->nullable();
            $t->timestamps();
            $t->unique(['shipment_id','external_event_id'], 'pc_shipment_event_external_uq');
            $t->index(['shipment_id','occurred_at']);
        });
    }
    public function down(): void {
        Schema::connection('priyasa')->dropIfExists('priyasa_shipment_events');
        Schema::connection('priyasa')->dropIfExists('priyasa_refunds');
        Schema::connection('priyasa')->table('priyasa_shipments', function (Blueprint $t) {
            $t->dropColumn(['provider_shipment_id','courier_name','shipped_at','delivered_at','cancelled_at']);
        });
        Schema::connection('priyasa')->table('priyasa_payment_transactions', function (Blueprint $t) { $t->dropColumn('refund_id'); });
        Schema::connection('priyasa')->table('priyasa_payments', function (Blueprint $t) { $t->dropIndex(['provider_payment_id','status']); $t->dropColumn(['method','refunded_amount','failed_at','refunded_at']); });
    }
};
