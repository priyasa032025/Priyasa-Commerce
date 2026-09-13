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
        $database = DB::connection('priyasa')->getDatabaseName();
        return DB::connection('priyasa')->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function ensureIndex(string $table, array $columns): void
    {
        $index = $table . '_' . implode('_', $columns) . '_index';
        if (!$this->hasIndex($table, $index)) {
            Schema::connection('priyasa')->table($table, function (Blueprint $t) use ($columns): void {
                $t->index($columns);
            });
        }
    }

    public function up(): void
    {
        $schema = Schema::connection('priyasa');

        foreach ([
            'priyasa_products' => ['source', 'external_id'],
            'priyasa_product_variants' => ['source', 'external_id'],
            'priyasa_customers' => ['source', 'external_id'],
        ] as $table => $indexColumns) {
            if (!$schema->hasTable($table)) {
                continue;
            }

            $schema->table($table, function (Blueprint $t) use ($table): void {
                if (!$this->columnExists($table, 'source')) {
                    $t->string('source', 32)->default('manual');
                }
                if (!$this->columnExists($table, 'external_id')) {
                    $t->string('external_id', 191)->nullable();
                }
            });

            $this->ensureIndex($table, $indexColumns);
        }

        if ($schema->hasTable('priyasa_orders')) {
            // WooCommerce/external channel imports use this stable external identifier.
            // This must exist before the composite index is created.
            if (!$schema->hasColumn('priyasa_orders', 'source_external_id')) {
                $schema->table('priyasa_orders', function (Blueprint $t): void {
                    $t->string('source_external_id', 191)->nullable()->after('source');
                });
            }

            $this->ensureIndex('priyasa_orders', ['source', 'source_external_id']);
        }

        if ($schema->hasTable('priyasa_customers') && $schema->hasColumn('priyasa_customers', 'phone')) {
            // Keep existing customer phone data intact while allowing longer normalized values.
            $schema->table('priyasa_customers', function (Blueprint $t): void {
                $t->string('phone', 32)->nullable()->change();
            });
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::connection('priyasa')->hasColumn($table, $column);
    }

    public function down(): void
    {
        // Forward-only compatibility migration. Do not remove production channel mappings.
    }
};
