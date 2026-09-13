<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_customer_tags')) Schema::connection('priyasa')->create('priyasa_customer_tags', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->string('tag',80); $t->string('value',180)->nullable(); $t->timestamps();
            $t->unique(['customer_id','tag']); $t->index('customer_id');
        });
        if (!Schema::connection('priyasa')->hasTable('priyasa_customer_notes')) Schema::connection('priyasa')->create('priyasa_customer_notes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->text('note'); $t->string('author_id',128)->nullable(); $t->timestamps();
            $t->index(['customer_id','created_at']);
        });
        if (!Schema::connection('priyasa')->hasTable('priyasa_customer_consents')) Schema::connection('priyasa')->create('priyasa_customer_consents', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->string('channel',32); $t->string('purpose',64); $t->boolean('granted')->default(false); $t->timestamp('granted_at')->nullable(); $t->timestamp('revoked_at')->nullable(); $t->string('source',64)->nullable(); $t->timestamps();
            $t->unique(['customer_id','channel','purpose'], 'pc_consent_customer_channel_purpose_uq');
        });
        if (!Schema::connection('priyasa')->hasTable('priyasa_customer_segments')) Schema::connection('priyasa')->create('priyasa_customer_segments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->string('segment',80); $t->string('source',40)->default('rule'); $t->timestamp('expires_at')->nullable(); $t->timestamps();
            $t->unique(['customer_id','segment']); $t->index(['segment','customer_id']);
        });
        if (!Schema::connection('priyasa')->hasTable('priyasa_customer_identity_links')) Schema::connection('priyasa')->create('priyasa_customer_identity_links', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->string('identity_type',40); $t->string('identity_value',255); $t->string('source',64)->nullable(); $t->boolean('verified')->default(false); $t->timestamps();
            $t->unique(['identity_type','identity_value'], 'pc_customer_identity_type_value_uq'); $t->index('customer_id');
        });
        if (!Schema::connection('priyasa')->hasTable('priyasa_customer_merge_events')) Schema::connection('priyasa')->create('priyasa_customer_merge_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('from_customer_id'); $t->unsignedBigInteger('to_customer_id'); $t->string('actor_id',128)->nullable(); $t->json('summary')->nullable(); $t->timestamps();
            $t->index(['from_customer_id','to_customer_id'], 'pc_customer_merge_from_to_idx');
        });
    }
    public function down(): void
    {
        foreach (['priyasa_customer_merge_events','priyasa_customer_identity_links','priyasa_customer_segments','priyasa_customer_consents','priyasa_customer_notes','priyasa_customer_tags'] as $t) if (Schema::connection('priyasa')->hasTable($t)) Schema::connection('priyasa')->drop($t);
    }
};
