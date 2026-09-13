<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_returns') && !Schema::connection('priyasa')->hasColumn('priyasa_returns', 'metadata')) {
            Schema::connection('priyasa')->table('priyasa_returns', function (Blueprint $table): void {
                $table->json('metadata')->nullable()->after('refund_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_returns') && Schema::connection('priyasa')->hasColumn('priyasa_returns', 'metadata')) {
            Schema::connection('priyasa')->table('priyasa_returns', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }
    }
};
