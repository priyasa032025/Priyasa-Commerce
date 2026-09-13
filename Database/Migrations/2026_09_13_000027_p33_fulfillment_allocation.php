<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void {
        Schema::connection('priyasa')->table('priyasa_warehouses', function (Blueprint $t) {
            if (!Schema::connection('priyasa')->hasColumn('priyasa_warehouses','priority')) $t->unsignedInteger('priority')->default(100)->index();
            if (!Schema::connection('priyasa')->hasColumn('priyasa_warehouses','supports_fulfillment')) $t->boolean('supports_fulfillment')->default(true)->index();
        });
        Schema::connection('priyasa')->create('priyasa_warehouse_serviceability', function(Blueprint $t){
            $t->id(); $t->foreignId('warehouse_id')->constrained('priyasa_warehouses')->cascadeOnDelete();
            $t->string('pincode',20)->index(); $t->boolean('serviceable')->default(true); $t->unsignedInteger('priority')->default(100);
            $t->timestamps(); $t->unique(['warehouse_id','pincode'], 'pc_wh_service_pincode_uq');
        });
        Schema::connection('priyasa')->create('priyasa_fulfillment_allocations', function(Blueprint $t){
            $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('order_item_id')->index();
            $t->foreignId('warehouse_id')->constrained('priyasa_warehouses')->cascadeOnDelete();
            $t->unsignedBigInteger('variant_id')->index(); $t->unsignedInteger('quantity');
            $t->string('status',30)->default('allocated')->index();
            $t->string('reference',120)->unique(); $t->timestamp('picked_at')->nullable(); $t->timestamp('packed_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->timestamps();
            $t->index(['order_id','status']);
        });
        Schema::connection('priyasa')->create('priyasa_warehouse_transfers', function(Blueprint $t){
            $t->id(); $t->string('reference',120)->unique(); $t->foreignId('from_warehouse_id')->constrained('priyasa_warehouses'); $t->foreignId('to_warehouse_id')->constrained('priyasa_warehouses');
            $t->unsignedBigInteger('variant_id')->index(); $t->unsignedInteger('quantity'); $t->unsignedInteger('received_quantity')->default(0); $t->string('status',30)->default('requested')->index(); $t->text('note')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { Schema::connection('priyasa')->dropIfExists('priyasa_warehouse_transfers'); Schema::connection('priyasa')->dropIfExists('priyasa_fulfillment_allocations'); Schema::connection('priyasa')->dropIfExists('priyasa_warehouse_serviceability'); if(Schema::connection('priyasa')->hasTable('priyasa_warehouses')) Schema::connection('priyasa')->table('priyasa_warehouses',function(Blueprint $t){ if(Schema::connection('priyasa')->hasColumn('priyasa_warehouses','priority'))$t->dropColumn('priority'); if(Schema::connection('priyasa')->hasColumn('priyasa_warehouses','supports_fulfillment'))$t->dropColumn('supports_fulfillment'); }); }
};
