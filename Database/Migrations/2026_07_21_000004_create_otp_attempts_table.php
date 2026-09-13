<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('otp_attempts')) {
            return;
        }

        Schema::connection('priyasa')->create('otp_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('otp_request_id');
            $table->foreign('otp_request_id', 'otp_attempts_request_fk')->references('id')->on('otp_requests')->cascadeOnDelete();
            $table->string('entered_otp', 255)->nullable();
            $table->boolean('success')->default(false);
            $table->string('provider')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamps();
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->index('success');
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('otp_attempts');
    }
};
