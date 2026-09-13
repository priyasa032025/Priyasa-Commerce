<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    protected $connection = 'priyasa'; public function up(): void { if(!Schema::connection('priyasa')->hasTable('priyasa_cms_versions')) Schema::connection('priyasa')->create('priyasa_cms_versions',function(Blueprint $t){$t->id();$t->string('page_key',120)->default('home');$t->unsignedBigInteger('version')->default(1);$t->json('snapshot');$t->string('status',20)->default('draft');$t->unsignedBigInteger('created_by')->nullable();$t->timestamp('published_at')->nullable();$t->timestamps();$t->index(['page_key','status','version']);}); if(!Schema::connection('priyasa')->hasColumn('priyasa_cms_sections','mobile_image_url')) Schema::connection('priyasa')->table('priyasa_cms_sections',fn(Blueprint $t)=>$t->text('mobile_image_url')->nullable()->after('image_url')); }
 public function down(): void {} };
