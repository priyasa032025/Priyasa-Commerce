<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    protected $connection = 'priyasa';
 public function up(): void {
  if (!Schema::connection('priyasa')->hasTable('priyasa_search_queries')) Schema::connection('priyasa')->create('priyasa_search_queries', function(Blueprint $t){$t->id();$t->string('query',255);$t->string('normalized_query',255);$t->unsignedBigInteger('user_id')->nullable();$t->string('session_id',120)->nullable();$t->unsignedInteger('result_count')->default(0);$t->unsignedInteger('result_clicks')->default(0);$t->timestamp('searched_at')->useCurrent();$t->index(['normalized_query','searched_at'], 'pc_search_norm_searched_idx');$t->index(['user_id','searched_at']);});
  if (!Schema::connection('priyasa')->hasTable('priyasa_product_relations')) Schema::connection('priyasa')->create('priyasa_product_relations', function(Blueprint $t){$t->id();$t->unsignedBigInteger('product_id');$t->unsignedBigInteger('related_product_id');$t->string('relation_type',50);$t->decimal('score',12,6)->default(0);$t->unsignedInteger('support_count')->default(0);$t->timestamps();$t->unique(['product_id','related_product_id','relation_type'], 'pc_product_relation_product_related_type_uq');$t->index(['product_id','relation_type','score'], 'pc_relation_product_type_score_idx');});
  if (!Schema::connection('priyasa')->hasTable('priyasa_search_synonyms')) Schema::connection('priyasa')->create('priyasa_search_synonyms', function(Blueprint $t){$t->id();$t->string('term',120)->unique();$t->json('synonyms');$t->boolean('is_active')->default(true);$t->timestamps();});
 }
 public function down(): void { foreach(['priyasa_search_synonyms','priyasa_product_relations','priyasa_search_queries'] as $t) if(Schema::connection('priyasa')->hasTable($t)) Schema::connection('priyasa')->drop($t); }
};
