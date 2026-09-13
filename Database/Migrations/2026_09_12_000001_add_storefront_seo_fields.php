<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'meta_title')) Schema::connection('priyasa')->table('priyasa_products', fn (Blueprint $t) => $t->string('meta_title')->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'meta_description')) Schema::connection('priyasa')->table('priyasa_products', fn (Blueprint $t) => $t->string('meta_description', 500)->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'canonical_url')) Schema::connection('priyasa')->table('priyasa_products', fn (Blueprint $t) => $t->text('canonical_url')->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_categories', 'meta_title')) Schema::connection('priyasa')->table('priyasa_categories', fn (Blueprint $t) => $t->string('meta_title')->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_categories', 'meta_description')) Schema::connection('priyasa')->table('priyasa_categories', fn (Blueprint $t) => $t->string('meta_description', 500)->nullable());
        if (!Schema::connection('priyasa')->hasColumn('priyasa_categories', 'canonical_url')) Schema::connection('priyasa')->table('priyasa_categories', fn (Blueprint $t) => $t->text('canonical_url')->nullable());
    }
    public function down(): void
    {
        foreach (['meta_title','meta_description','canonical_url'] as $column) {
            if (Schema::connection('priyasa')->hasColumn('priyasa_products', $column)) Schema::connection('priyasa')->table('priyasa_products', fn (Blueprint $t) => $t->dropColumn($column));
            if (Schema::connection('priyasa')->hasColumn('priyasa_categories', $column)) Schema::connection('priyasa')->table('priyasa_categories', fn (Blueprint $t) => $t->dropColumn($column));
        }
    }
};
