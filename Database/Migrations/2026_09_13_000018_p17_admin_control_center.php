<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void {
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_bulk_jobs')) {
            Schema::connection('priyasa')->create('priyasa_admin_bulk_jobs', function (Blueprint $t) {
                $t->id();
                $t->string('operation', 80);
                $t->string('status', 30)->default('queued')->index();
                $t->unsignedBigInteger('created_by')->nullable()->index();
                $t->unsignedInteger('total')->default(0);
                $t->unsignedInteger('processed')->default(0);
                $t->unsignedInteger('failed')->default(0);
                $t->json('payload')->nullable();
                $t->text('error')->nullable();
                $t->timestamps();
                $t->index(['status','created_at']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_admin_saved_views')) {
            Schema::connection('priyasa')->create('priyasa_admin_saved_views', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('admin_user_id')->index();
                $t->string('name', 100);
                $t->string('resource', 60);
                $t->json('filters')->nullable();
                $t->json('columns')->nullable();
                $t->timestamps();
                $t->unique(['admin_user_id','resource','name']);
            });
        }
    }
    public function down(): void {
        Schema::connection('priyasa')->dropIfExists('priyasa_admin_saved_views');
        Schema::connection('priyasa')->dropIfExists('priyasa_admin_bulk_jobs');
    }
};
