<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_action_idempotency')) {
            Schema::connection('priyasa')->create('priyasa_admin_action_idempotency', function (Blueprint $table): void {
                $table->id();
                $table->string('idempotency_key', 128)->unique();
                $table->string('action', 100);
                $table->string('actor_id', 100)->nullable()->index();
                $table->string('resource_type', 100)->nullable();
                $table->string('resource_id', 100)->nullable();
                $table->string('request_hash', 64);
                $table->json('response')->nullable();
                $table->unsignedSmallInteger('status_code')->default(200);
                $table->timestamps();
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_action_audits')) {
            Schema::connection('priyasa')->create('priyasa_admin_action_audits', function (Blueprint $table): void {
                $table->id();
                $table->string('actor_id', 100)->nullable()->index();
                $table->string('action', 100)->index();
                $table->string('resource_type', 100)->nullable()->index();
                $table->string('resource_id', 100)->nullable()->index();
                $table->string('permission', 100)->nullable()->index();
                $table->string('idempotency_key', 128)->nullable()->index();
                $table->string('result', 32)->default('success')->index();
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_admin_action_audits');
        Schema::connection('priyasa')->dropIfExists('priyasa_admin_action_idempotency');
    }
};
