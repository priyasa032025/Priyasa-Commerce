<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    protected $connection = 'priyasa';
 public function up(){
  Schema::connection('priyasa')->table('priyasa_commerce_conversations',function(Blueprint $t){if(!Schema::connection('priyasa')->hasColumn('priyasa_commerce_conversations','phone'))$t->string('phone',40)->nullable()->index();});
  Schema::connection('priyasa')->table('priyasa_commerce_conversation_messages',function(Blueprint $t){if(!Schema::connection('priyasa')->hasColumn('priyasa_commerce_conversation_messages','provider_message_id'))$t->string('provider_message_id',191)->nullable(); if(!Schema::connection('priyasa')->hasColumn('priyasa_commerce_conversation_messages','provider_status'))$t->string('provider_status',30)->nullable()->index(); if(!Schema::connection('priyasa')->hasColumn('priyasa_commerce_conversation_messages','sent_at'))$t->timestamp('sent_at')->nullable();});
  if (Schema::connection('priyasa')->hasTable('priyasa_commerce_conversation_messages') && Schema::connection('priyasa')->hasColumn('priyasa_commerce_conversation_messages','provider_message_id')) { $db=\Illuminate\Support\Facades\DB::connection('priyasa'); $exists=$db->table('information_schema.statistics')->where('table_schema',$db->getDatabaseName())->where('table_name','priyasa_commerce_conversation_messages')->where('index_name','pc_commerce_msg_provider_id_uq')->exists(); if(!$exists) Schema::connection('priyasa')->table('priyasa_commerce_conversation_messages',fn(Blueprint $t)=>$t->unique('provider_message_id','pc_commerce_msg_provider_id_uq')); }
  Schema::connection('priyasa')->create('priyasa_whatsapp_webhook_events',function(Blueprint $t){$t->id();$t->string('delivery_id',191)->unique();$t->string('event_type',80)->nullable();$t->json('payload');$t->timestamp('processed_at')->nullable();$t->text('error')->nullable();$t->timestamps();});
 } public function down(){Schema::connection('priyasa')->dropIfExists('priyasa_whatsapp_webhook_events');}
};
