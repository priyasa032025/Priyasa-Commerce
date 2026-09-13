<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    protected $connection = 'priyasa';
 public function up(): void { Schema::connection('priyasa')->create('push_telemetry_events',function(Blueprint $t){$t->id();$t->string('event',64)->index();$t->string('status',32)->nullable();$t->string('detail',300)->nullable();$t->string('device_id',100)->index();$t->string('session_id',100)->index();$t->string('browser',50)->nullable()->index();$t->string('os',50)->nullable()->index();$t->string('device_type',30)->nullable();$t->string('in_app_browser',50)->nullable()->index();$t->string('permission',30)->nullable();$t->string('path',500)->nullable();$t->string('landing_path',500)->nullable();$t->string('referrer',500)->nullable();$t->string('source',100)->nullable();$t->string('plugin_version',30)->nullable();$t->string('ip_hash',64)->nullable();$t->timestamps();$t->index(['created_at','event']);});}
 public function down(): void {Schema::connection('priyasa')->dropIfExists('push_telemetry_events');}
};
