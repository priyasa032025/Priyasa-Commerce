<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    protected $connection = 'priyasa';
 public function up(): void {
  if (!Schema::connection('priyasa')->hasTable('priyasa_notification_templates')) Schema::connection('priyasa')->create('priyasa_notification_templates', function(Blueprint $t){$t->id();$t->string('key')->unique();$t->string('channel',30);$t->string('locale',20)->default('en-IN');$t->string('subject')->nullable();$t->text('body');$t->json('metadata')->nullable();$t->boolean('is_active')->default(true);$t->timestamps();});
  if (!Schema::connection('priyasa')->hasTable('priyasa_notification_preferences')) Schema::connection('priyasa')->create('priyasa_notification_preferences', function(Blueprint $t){$t->id();$t->unsignedBigInteger('customer_id')->unique();$t->json('preferences')->nullable();$t->timestamps();});
  if (!Schema::connection('priyasa')->hasTable('priyasa_notification_outbox')) Schema::connection('priyasa')->create('priyasa_notification_outbox', function(Blueprint $t){$t->id();$t->unsignedBigInteger('customer_id')->nullable();$t->string('channel',30);$t->string('event_key',120);$t->string('dedupe_key',191)->unique();$t->string('recipient',320);$t->string('template_key',120)->nullable();$t->json('payload')->nullable();$t->string('status',30)->default('pending');$t->unsignedInteger('attempts')->default(0);$t->timestamp('available_at')->nullable();$t->timestamp('sent_at')->nullable();$t->text('last_error')->nullable();$t->timestamps();$t->index(['status','available_at']);});
  if (!Schema::connection('priyasa')->hasTable('priyasa_notification_deliveries')) Schema::connection('priyasa')->create('priyasa_notification_deliveries', function(Blueprint $t){$t->id();$t->foreignId('outbox_id')->constrained('priyasa_notification_outbox')->cascadeOnDelete();$t->string('provider',50);$t->string('provider_message_id')->nullable();$t->string('status',30);$t->text('response')->nullable();$t->timestamp('delivered_at')->nullable();$t->timestamps();$t->index(['provider','provider_message_id'], 'pc_notify_delivery_provider_msg_idx');});
  if (Schema::connection('priyasa')->hasTable('priyasa_notification_deliveries')) {
      $db = \Illuminate\Support\Facades\DB::connection('priyasa');
      $exists = $db->table('information_schema.statistics')->where('table_schema',$db->getDatabaseName())->where('table_name','priyasa_notification_deliveries')->where('index_name','pc_notify_delivery_provider_msg_idx')->exists();
      if (!$exists) Schema::connection('priyasa')->table('priyasa_notification_deliveries', fn(Blueprint $t) => $t->index(['provider','provider_message_id'], 'pc_notify_delivery_provider_msg_idx'));
  }
 }
 public function down(): void { foreach(['priyasa_notification_deliveries','priyasa_notification_outbox','priyasa_notification_preferences','priyasa_notification_templates'] as $t) Schema::connection('priyasa')->dropIfExists($t); }
};
