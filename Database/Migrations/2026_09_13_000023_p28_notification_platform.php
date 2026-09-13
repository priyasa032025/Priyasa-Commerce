<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_notification_devices')) {
            Schema::connection('priyasa')->create('priyasa_notification_devices', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->string('device_id', 191)->index();
                $t->string('platform', 30)->default('android')->index();
                $t->text('fcm_token');
                $t->string('token_hash', 64)->unique();
                $t->boolean('enabled')->default(true)->index();
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamp('invalidated_at')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->unique(['user_id','device_id']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_notification_inbox')) {
            Schema::connection('priyasa')->create('priyasa_notification_inbox', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->string('dedupe_key', 191)->unique();
                $t->string('type', 80)->index();
                $t->string('title', 255);
                $t->text('body');
                $t->json('data')->nullable();
                $t->string('deep_link', 500)->nullable();
                $t->timestamp('read_at')->nullable()->index();
                $t->timestamp('expires_at')->nullable()->index();
                $t->timestamps();
            });
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_notification_deliveries')) {
            Schema::connection('priyasa')->table('priyasa_notification_deliveries', function (Blueprint $t) {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_notification_deliveries','provider')) $t->string('provider', 40)->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_notification_deliveries','device_id')) $t->unsignedBigInteger('device_id')->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_notification_deliveries','provider_message_id')) $t->string('provider_message_id', 191)->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_notification_deliveries','failed_at')) $t->timestamp('failed_at')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_notification_deliveries','last_error')) $t->text('last_error')->nullable();
            });
        }
    }
    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_notification_inbox');
        Schema::connection('priyasa')->dropIfExists('priyasa_notification_devices');
    }
};
