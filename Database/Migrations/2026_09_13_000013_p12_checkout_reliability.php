<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    protected $connection = 'priyasa';
 public function up(): void {
  Schema::connection('priyasa')->create('priyasa_inventory_reservations', function(Blueprint $t){
   $t->id(); $t->foreignId('variant_id')->constrained('priyasa_product_variants')->cascadeOnDelete();
   $t->foreignId('order_id')->nullable()->constrained('priyasa_orders')->nullOnDelete();
   $t->string('reference',191); $t->unsignedInteger('quantity'); $t->string('status',20)->default('active');
   $t->timestamp('expires_at')->nullable(); $t->timestamp('released_at')->nullable(); $t->timestamp('committed_at')->nullable(); $t->timestamps();
   $t->unique(['variant_id','reference'], 'pc_reservation_variant_ref_uq'); $t->index(['status','expires_at']); $t->index(['order_id','status']);
  });
  Schema::connection('priyasa')->table('priyasa_payment_transactions', function(Blueprint $t){
   if (!Schema::connection('priyasa')->hasColumn('priyasa_payment_transactions','idempotency_key')) $t->string('idempotency_key',191)->nullable()->unique();
  });
  Schema::connection('priyasa')->table('priyasa_orders', function(Blueprint $t){
   if (!Schema::connection('priyasa')->hasColumn('priyasa_orders','version')) $t->unsignedInteger('version')->default(1);
   if (!Schema::connection('priyasa')->hasColumn('priyasa_orders','cancelled_at')) $t->timestamp('cancelled_at')->nullable();
  });
  Schema::connection('priyasa')->table('priyasa_shipments', function(Blueprint $t){
   if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments','version')) $t->unsignedInteger('version')->default(1);
   if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments','shipped_at')) $t->timestamp('shipped_at')->nullable();
   if (!Schema::connection('priyasa')->hasColumn('priyasa_shipments','delivered_at')) $t->timestamp('delivered_at')->nullable();
  });
 }
 public function down(): void {
  Schema::connection('priyasa')->dropIfExists('priyasa_inventory_reservations');
  foreach(['priyasa_payment_transactions','priyasa_orders','priyasa_shipments'] as $table){ if(Schema::connection('priyasa')->hasTable($table)){ Schema::connection('priyasa')->table($table,function(Blueprint $t)use($table){ foreach(['idempotency_key','version','cancelled_at','shipped_at','delivered_at'] as $c){ if(Schema::connection('priyasa')->hasColumn($table,$c)) $t->dropColumn($c); } }); } }
 }
};
