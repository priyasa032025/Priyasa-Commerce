<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void {
        Schema::connection('priyasa')->table('priyasa_payment_transactions', function (Blueprint $table) { $table->unique(['provider','provider_reference','type'], 'priyasa_payment_txn_dedupe'); });
        Schema::connection('priyasa')->table('priyasa_payments', function (Blueprint $table) { $table->index(['provider','provider_order_id'], 'priyasa_payment_provider_order_idx'); });
    }
    public function down(): void {
        Schema::connection('priyasa')->table('priyasa_payment_transactions', function (Blueprint $table) { $table->dropUnique('priyasa_payment_txn_dedupe'); });
        Schema::connection('priyasa')->table('priyasa_payments', function (Blueprint $table) { $table->dropIndex('priyasa_payment_provider_order_idx'); });
    }
};
