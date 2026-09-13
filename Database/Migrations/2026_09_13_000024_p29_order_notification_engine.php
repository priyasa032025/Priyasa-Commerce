<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void {
        if (!Schema::connection('priyasa')->hasTable('priyasa_notification_preferences')) {
            Schema::connection('priyasa')->create('priyasa_notification_preferences', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('user_id')->nullable()->index(); $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->string('event_type', 100); $t->string('channel', 30); $t->boolean('enabled')->default(true); $t->timestamps();
                $t->unique(['user_id','event_type','channel'], 'pc_notify_pref_user_event_channel_uq');
            });
        }
    }
    public function down(): void { Schema::connection('priyasa')->dropIfExists('priyasa_notification_preferences'); }
};
