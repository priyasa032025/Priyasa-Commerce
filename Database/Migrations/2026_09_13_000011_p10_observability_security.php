<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    protected $connection = 'priyasa';
 public function up(): void {
  if (!Schema::connection('priyasa')->hasTable('priyasa_audit_logs')) Schema::connection('priyasa')->create('priyasa_audit_logs', function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('action',120);$t->string('entity_type',160)->nullable();$t->string('entity_id',120)->nullable();$t->string('request_id',100)->nullable();$t->string('ip_address',64)->nullable();$t->string('user_agent',500)->nullable();$t->json('before')->nullable();$t->json('after')->nullable();$t->json('metadata')->nullable();$t->timestamps();$t->index(['entity_type','entity_id']);$t->index(['action','created_at']);$t->index(['request_id']);});
  if (!Schema::connection('priyasa')->hasTable('priyasa_api_request_metrics')) Schema::connection('priyasa')->create('priyasa_api_request_metrics', function(Blueprint $t){$t->id();$t->string('request_id',100)->nullable();$t->string('method',12);$t->string('path',500);$t->unsignedSmallInteger('status_code')->nullable();$t->unsignedInteger('duration_ms')->nullable();$t->unsignedBigInteger('user_id')->nullable();$t->string('ip_address',64)->nullable();$t->timestamps();$t->index(['path','created_at']);$t->index(['status_code','created_at'], 'pc_api_metric_status_time_idx');});
  if (!Schema::connection('priyasa')->hasTable('priyasa_idempotency_keys')) Schema::connection('priyasa')->create('priyasa_idempotency_keys', function(Blueprint $t){$t->id();$t->string('key',191);$t->string('scope',191);$t->unsignedBigInteger('user_id')->nullable();$t->unsignedSmallInteger('status_code')->nullable();$t->json('response')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();$t->unique(['key','scope']);$t->index(['expires_at']);});
 }
 public function down(): void {foreach(['priyasa_idempotency_keys','priyasa_api_request_metrics','priyasa_audit_logs'] as $t) Schema::connection('priyasa')->dropIfExists($t);}
};
