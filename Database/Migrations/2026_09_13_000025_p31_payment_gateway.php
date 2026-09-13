<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_payments')) {
            Schema::connection('priyasa')->table('priyasa_payments', function (Blueprint $table): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_payments', 'failed_at')) $table->timestamp('failed_at')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_payments', 'refunded_amount')) $table->decimal('refunded_amount', 12, 2)->default(0);
                if (!Schema::connection('priyasa')->hasColumn('priyasa_payments', 'refunded_at')) $table->timestamp('refunded_at')->nullable();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_payments', 'failure_code')) $table->string('failure_code', 80)->nullable();
            });
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_payment_transactions') && !Schema::connection('priyasa')->hasColumn('priyasa_payment_transactions', 'idempotency_key')) {
            Schema::connection('priyasa')->table('priyasa_payment_transactions', function (Blueprint $table): void {
                $table->string('idempotency_key', 191)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_payment_transactions') && Schema::connection('priyasa')->hasColumn('priyasa_payment_transactions', 'idempotency_key')) {
            Schema::connection('priyasa')->table('priyasa_payment_transactions', function (Blueprint $table): void { $table->dropUnique(['idempotency_key']); $table->dropColumn('idempotency_key'); });
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_payments')) {
            Schema::connection('priyasa')->table('priyasa_payments', function (Blueprint $table): void {
                foreach (['failure_code','failed_at','refunded_amount','refunded_at'] as $column) if (Schema::connection('priyasa')->hasColumn('priyasa_payments', $column)) $table->dropColumn($column);
            });
        }
    }
};
