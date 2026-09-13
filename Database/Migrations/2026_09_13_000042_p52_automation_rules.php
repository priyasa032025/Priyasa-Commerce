<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_automation_rules')) {
            Schema::connection('priyasa')->create('priyasa_automation_rules', function (Blueprint $t): void {
                $t->id(); $t->string('event_key',120); $t->string('name',190);
                $t->boolean('is_active')->default(true); $t->json('config')->nullable(); $t->timestamps();
                $t->index(['event_key','is_active']);
            });
        }
        if (!Schema::connection('priyasa')->hasTable('priyasa_automation_runs')) {
            Schema::connection('priyasa')->create('priyasa_automation_runs', function (Blueprint $t): void {
                $t->id(); $t->foreignId('rule_id')->constrained('priyasa_automation_rules')->cascadeOnDelete();
                $t->string('event_key',120); $t->string('entity_type',120)->nullable(); $t->unsignedBigInteger('entity_id')->nullable();
                $t->json('payload')->nullable(); $t->string('status',30)->default('queued'); $t->timestamps();
                $t->unique(['rule_id','event_key','entity_type','entity_id'],'p52_auto_run_unique');
                $t->index(['status','created_at']);
            });
        }
    }
    public function down(): void
    {
        Schema::connection('priyasa')->dropIfExists('priyasa_automation_runs'); Schema::connection('priyasa')->dropIfExists('priyasa_automation_rules');
    }
};
