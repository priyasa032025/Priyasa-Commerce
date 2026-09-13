<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void {
        if (Schema::connection('priyasa')->hasTable('priyasa_shipments')) {
            Schema::connection('priyasa')->table('priyasa_shipments', function (Blueprint $t) {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments','warehouse_id')) $t->unsignedBigInteger('warehouse_id')->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments','label_url')) $t->text('label_url')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments','pickup_scheduled_at')) $t->timestamp('pickup_scheduled_at')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments','rto_at')) $t->timestamp('rto_at')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments','last_synced_at')) $t->timestamp('last_synced_at')->nullable();
            });
            try { Schema::connection('priyasa')->table('priyasa_shipments', fn(Blueprint $t) => $t->dropUnique(['order_id'])); } catch (Throwable $e) { /* already non-unique */ }
            try { Schema::connection('priyasa')->table('priyasa_shipments', fn(Blueprint $t) => $t->index(['order_id','warehouse_id'])); } catch (Throwable $e) { /* already indexed */ }
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_shipment_events') && !Schema::connection('priyasa')->hasColumn('priyasa_shipment_events','description')) {
            Schema::connection('priyasa')->table('priyasa_shipment_events', function(Blueprint $t){ $t->text('description')->nullable(); });
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_shipment_items') && !Schema::connection('priyasa')->hasColumn('priyasa_shipment_items','variant_id')) {
            Schema::connection('priyasa')->table('priyasa_shipment_items', function(Blueprint $t){ $t->unsignedBigInteger('variant_id')->nullable()->index(); });
        }
    }
    public function down(): void {
        if (Schema::connection('priyasa')->hasTable('priyasa_shipments')) Schema::connection('priyasa')->table('priyasa_shipments', function(Blueprint $t){
            foreach (['warehouse_id','label_url','pickup_scheduled_at','rto_at','last_synced_at'] as $c) if(Schema::connection('priyasa')->hasColumn('priyasa_shipments',$c)) $t->dropColumn($c);
        });
    }
};
