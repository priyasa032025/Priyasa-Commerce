<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_idempotency_keys')) {
            Schema::connection('priyasa')->table('priyasa_idempotency_keys', function (Blueprint $t): void {
                if (!Schema::connection('priyasa')->hasColumn('priyasa_idempotency_keys','user_id')) $t->unsignedBigInteger('user_id')->nullable()->index();
                if (!Schema::connection('priyasa')->hasColumn('priyasa_idempotency_keys','expires_at')) $t->timestamp('expires_at')->nullable()->index();
            });
        }
    }
    public function down(): void {}
};
