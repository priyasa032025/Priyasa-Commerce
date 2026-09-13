<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        Schema::connection('priyasa')->table('priyasa_customers', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->index()->after('order_count');
        });
    }

    public function down(): void
    {
        Schema::connection('priyasa')->table('priyasa_customers', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
