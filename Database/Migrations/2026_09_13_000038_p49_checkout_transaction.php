<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_orders')) return;
        Schema::connection('priyasa')->table('priyasa_orders', function (Blueprint $table): void {
            if (!Schema::connection('priyasa')->hasColumn('priyasa_orders', 'checkout_quote_hash')) {
                $table->string('checkout_quote_hash', 64)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_orders') && Schema::connection('priyasa')->hasColumn('priyasa_orders', 'checkout_quote_hash')) {
            Schema::connection('priyasa')->table('priyasa_orders', function (Blueprint $table): void { $table->dropColumn('checkout_quote_hash'); });
        }
    }
};
