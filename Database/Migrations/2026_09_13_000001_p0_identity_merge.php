<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::connection('priyasa')->hasTable('priyasa_customers') && !Schema::connection('priyasa')->hasColumn('priyasa_customers', 'user_id')) {
            Schema::connection('priyasa')->table('priyasa_customers', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->index('user_id');
                $table->unique('user_id', 'priyasa_customers_user_id_unique');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'id')) {
            $userPhoneColumn = Schema::hasColumn('users', 'mobile')
                ? 'mobile'
                : (Schema::hasColumn('users', 'phone') ? 'phone' : null);

            if ($userPhoneColumn && Schema::connection('priyasa')->hasTable('priyasa_customers')) {
                DB::connection('priyasa')->table('priyasa_customers')
                    ->select(['id', 'phone'])
                    ->whereNull('user_id')
                    ->orderBy('id')
                    ->chunkById(250, function ($customers) use ($userPhoneColumn): void {
                        foreach ($customers as $customer) {
                            $digits = preg_replace('/\D+/', '', (string) $customer->phone) ?? '';
                            if ($digits === '') {
                                continue;
                            }

                            $candidates = array_values(array_unique(array_filter([
                                $digits,
                                strlen($digits) === 10 ? '+91'.$digits : null,
                                strlen($digits) === 10 ? '91'.$digits : null,
                                strlen($digits) === 12 && str_starts_with($digits, '91') ? substr($digits, 2) : null,
                                strlen($digits) === 12 && str_starts_with($digits, '91') ? '+'.$digits : null,
                            ])));

                            $user = DB::table('users')
                                ->select('id')
                                ->whereIn($userPhoneColumn, $candidates)
                                ->orderBy('id')
                                ->first();

                            if ($user) {
                                // Respect the unique user_id mapping. A customer row is
                                // linked only when that user is not already linked elsewhere.
                                $alreadyLinked = DB::connection('priyasa')->table('priyasa_customers')
                                    ->where('user_id', $user->id)
                                    ->where('id', '!=', $customer->id)
                                    ->exists();

                                if (!$alreadyLinked) {
                                    DB::connection('priyasa')->table('priyasa_customers')
                                        ->where('id', $customer->id)
                                        ->update(['user_id' => $user->id]);
                                }
                            }
                        }
                    });
            }
        }

        // Some deployed admin builds already expect this flag on users. Adding it
        // here makes those builds forward-compatible with the merged module.
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'is_blocked')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_blocked')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_blocked')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['is_blocked']);
                $table->dropColumn('is_blocked');
            });
        }

        if (Schema::connection('priyasa')->hasTable('priyasa_customers') && Schema::connection('priyasa')->hasColumn('priyasa_customers', 'user_id')) {
            Schema::connection('priyasa')->table('priyasa_customers', function (Blueprint $table): void {
                $table->dropUnique('priyasa_customers_user_id_unique');
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
