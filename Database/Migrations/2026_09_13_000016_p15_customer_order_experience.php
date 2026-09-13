<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
 public function up(): void {
  if (Schema::connection('priyasa')->hasTable('priyasa_order_status_histories')) {
   Schema::connection('priyasa')->table('priyasa_order_status_histories', function(Blueprint $t){
    if (!Schema::connection('priyasa')->hasIndex('priyasa_order_status_histories',['order_id','created_at'])) $t->index(['order_id','created_at'],'posh_order_created_idx');
   });
  }
 }
 public function down(): void {
  if (Schema::connection('priyasa')->hasTable('priyasa_order_status_histories')) Schema::connection('priyasa')->table('priyasa_order_status_histories',fn(Blueprint $t)=>$t->dropIndex('posh_order_created_idx'));
 }
};
