<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        Schema::connection('priyasa')->create('priyasa_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('priyasa_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('priyasa_customers')->restrictOnDelete();
            $table->string('status', 24)->default('requested');
            $table->string('reason')->nullable();
            $table->text('customer_note')->nullable();
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::connection('priyasa')->create('priyasa_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('priyasa_orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('priyasa_payments')->nullOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('priyasa_returns')->nullOnDelete();
            $table->string('provider', 32)->nullable();
            $table->string('provider_ref')->nullable()->index();
            $table->string('status', 24)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::connection('priyasa')->create('priyasa_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 128);
            $table->string('actor_type', 32)->nullable();
            $table->string('actor_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('data')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_audit_logs');
        Schema::connection('priyasa')->dropIfExists('priyasa_refunds');
        Schema::connection('priyasa')->dropIfExists('priyasa_returns');
    }
};