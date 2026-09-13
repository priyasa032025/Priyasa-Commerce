<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('device_tokens')) {
            return;
        }

        Schema::connection('priyasa')->create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->nullable()->index();
            $table->string('token', 4096)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('phone_number')->nullable()->index();
            $table->string('guest_id')->nullable()->index();
            $table->string('platform')->nullable()->index();
            $table->string('device')->nullable();
            $table->string('browser')->nullable();
            $table->string('language', 10)->nullable();
            $table->string('timezone')->nullable();
            $table->string('permission')->nullable();
            $table->string('channel')->nullable();
            $table->string('delivery_target')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('status')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_active')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_failure_code')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('device_tokens');
    }
};
