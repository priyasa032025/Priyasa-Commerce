<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('otp_requests')) {
            return;
        }

        Schema::connection('priyasa')->create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('mobile', 32)->index();
            $table->string('otp_hash', 255);
            $table->string('purpose', 50)->default('login')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('provider', 100)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->string('channel', 50)->nullable();
            $table->string('device_id', 255)->nullable()->index();
            $table->text('device_token')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('otp_requests');
    }
};
