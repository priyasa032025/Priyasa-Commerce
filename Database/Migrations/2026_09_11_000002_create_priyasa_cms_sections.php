<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_cms_sections')) {
            Schema::connection('priyasa')->create('priyasa_cms_sections', function (Blueprint $t) {
                $t->id();
                $t->string('key', 120)->unique();
                $t->string('type', 80)->default('banner');
                $t->string('title')->nullable();
                $t->string('subtitle')->nullable();
                $t->text('image_url')->nullable();
                $t->string('cta_label')->nullable();
                $t->text('cta_href')->nullable();
                $t->json('content')->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamp('starts_at')->nullable();
                $t->timestamp('ends_at')->nullable();
                $t->timestamps();
                $t->index(['is_active', 'sort_order']);
                $t->index(['starts_at', 'ends_at']);
            });
            return;
        }

        Schema::connection('priyasa')->table('priyasa_cms_sections', function (Blueprint $t) {
            if (!Schema::connection('priyasa')->hasColumn('priyasa_cms_sections', 'type')) $t->string('type', 80)->default('banner')->after('key');
            if (!Schema::connection('priyasa')->hasColumn('priyasa_cms_sections', 'subtitle')) $t->string('subtitle')->nullable()->after('title');
            if (!Schema::connection('priyasa')->hasColumn('priyasa_cms_sections', 'image_url')) $t->text('image_url')->nullable()->after('subtitle');
            if (!Schema::connection('priyasa')->hasColumn('priyasa_cms_sections', 'cta_label')) $t->string('cta_label')->nullable()->after('image_url');
            if (!Schema::connection('priyasa')->hasColumn('priyasa_cms_sections', 'cta_href')) $t->text('cta_href')->nullable()->after('cta_label');
            if (!Schema::connection('priyasa')->hasColumn('priyasa_cms_sections', 'starts_at')) $t->timestamp('starts_at')->nullable()->after('is_active');
            if (!Schema::connection('priyasa')->hasColumn('priyasa_cms_sections', 'ends_at')) $t->timestamp('ends_at')->nullable()->after('starts_at');
        });
    }

    public function down(): void { }
};
