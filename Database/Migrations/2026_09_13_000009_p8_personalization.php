<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';

    private function hasIndex(string $table, string $index): bool
    {
        return DB::connection('priyasa')->table('information_schema.statistics')
            ->where('table_schema', DB::connection('priyasa')->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function ensureIndex(string $table, array $columns, string $index, bool $unique = false): void
    {
        if ($this->hasIndex($table, $index)) {
            return;
        }

        $schema = Schema::connection('priyasa');
        $schema->table($table, function (Blueprint $t) use ($columns, $index, $unique): void {
            if ($unique) {
                $t->unique($columns, $index);
            } else {
                $t->index($columns, $index);
            }
        });
    }

    public function up(): void
    {
        $schema = Schema::connection('priyasa');

        if (!$schema->hasTable('priyasa_customer_events')) {
            $schema->create('priyasa_customer_events', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->unsignedBigInteger('customer_id')->nullable();
                $t->string('session_id', 120)->nullable();
                $t->string('event_type', 50);
                $t->unsignedBigInteger('product_id')->nullable();
                $t->unsignedBigInteger('variant_id')->nullable();
                $t->string('query', 255)->nullable();
                $t->json('metadata')->nullable();
                $t->timestamp('occurred_at')->useCurrent();
            });
        }

        $this->ensureIndex('priyasa_customer_events', ['user_id', 'event_type', 'occurred_at'], 'pc_events_user_type_time_idx');
        $this->ensureIndex('priyasa_customer_events', ['session_id', 'event_type', 'occurred_at'], 'pc_events_session_type_time_idx');
        $this->ensureIndex('priyasa_customer_events', ['product_id', 'event_type', 'occurred_at'], 'pc_events_product_type_time_idx');

        if (!$schema->hasTable('priyasa_recommendation_cache')) {
            $schema->create('priyasa_recommendation_cache', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('session_id', 120)->nullable();
                $t->string('slot', 60);
                $t->json('product_ids');
                $t->timestamp('expires_at');
                $t->timestamps();
            });
        }

        $this->ensureIndex('priyasa_recommendation_cache', ['user_id', 'slot'], 'pc_rec_cache_user_slot_idx');
        $this->ensureIndex('priyasa_recommendation_cache', ['session_id', 'slot'], 'pc_rec_cache_session_slot_idx');
        $this->ensureIndex('priyasa_recommendation_cache', ['expires_at'], 'pc_rec_cache_expires_idx');

        if (!$schema->hasTable('priyasa_product_affinities')) {
            $schema->create('priyasa_product_affinities', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('related_product_id');
                $t->string('reason', 50)->default('similar');
                $t->decimal('score', 12, 6)->default(0);
                $t->unsignedInteger('support_count')->default(0);
                $t->timestamps();
            });
        }

        // Explicit short names avoid MySQL's 64-character identifier limit.
        $this->ensureIndex(
            'priyasa_product_affinities',
            ['product_id', 'related_product_id', 'reason'],
            'pc_affinity_product_related_reason_uq',
            true
        );
        $this->ensureIndex('priyasa_product_affinities', ['product_id', 'score'], 'pc_affinity_product_score_idx');
    }

    public function down(): void
    {
        foreach (['priyasa_product_affinities', 'priyasa_recommendation_cache', 'priyasa_customer_events'] as $table) {
            if (Schema::connection('priyasa')->hasTable($table)) {
                Schema::connection('priyasa')->drop($table);
            }
        }
    }
};
