<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_orders') && !Schema::connection('priyasa')->hasColumn('priyasa_orders', 'checkout_idempotency_key')) {
            Schema::connection('priyasa')->table('priyasa_orders', function (Blueprint $table): void {
                $table->string('checkout_idempotency_key', 191)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_orders') && Schema::connection('priyasa')->hasColumn('priyasa_orders', 'checkout_idempotency_key')) {
            Schema::connection('priyasa')->table('priyasa_orders', function (Blueprint $table): void {
                $table->dropUnique(['checkout_idempotency_key']);
                $table->dropColumn('checkout_idempotency_key');
            });
        }
    }
};
