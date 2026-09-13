<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_shipment_items')) {
            Schema::connection('priyasa')->create('priyasa_shipment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipment_id')->constrained('priyasa_shipments')->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained('priyasa_order_items')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->timestamps();
                $table->unique(['shipment_id', 'order_item_id']);
                $table->index(['order_item_id', 'shipment_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_shipment_events')) {
            Schema::connection('priyasa')->create('priyasa_shipment_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipment_id')->constrained('priyasa_shipments')->cascadeOnDelete();
                $table->string('status', 50);
                $table->string('code', 80)->nullable();
                $table->string('location', 191)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['shipment_id', 'occurred_at']);
                $table->unique(['shipment_id', 'code', 'occurred_at']);
            });
        }
        if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments', 'provider_shipment_id')) Schema::connection('priyasa')->table('priyasa_shipments', fn(Blueprint $t) => $t->string('provider_shipment_id', 100)->nullable()->index());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments', 'courier_name')) Schema::connection('priyasa')->table('priyasa_shipments', fn(Blueprint $t) => $t->string('courier_name', 100)->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments', 'shipped_at')) Schema::connection('priyasa')->table('priyasa_shipments', fn(Blueprint $t) => $t->timestamp('shipped_at')->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments', 'delivered_at')) Schema::connection('priyasa')->table('priyasa_shipments', fn(Blueprint $t) => $t->timestamp('delivered_at')->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments', 'cancelled_at')) Schema::connection('priyasa')->table('priyasa_shipments', fn(Blueprint $t) => $t->timestamp('cancelled_at')->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments', 'last_synced_at')) Schema::connection('priyasa')->table('priyasa_shipments', fn(Blueprint $t) => $t->timestamp('last_synced_at')->nullable());
    }
    public function down(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_shipment_events')) Schema::connection('priyasa')->drop('priyasa_shipment_events');
        if (Schema::connection('priyasa')->hasTable('priyasa_shipment_items')) Schema::connection('priyasa')->drop('priyasa_shipment_items');
    }
};
