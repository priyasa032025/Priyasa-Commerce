<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'priyasa';

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::connection('priyasa')->hasColumn($table, $column);
    }

    private function hasIndex(string $table, string $index): bool
    {
        $db = DB::connection('priyasa');
        $database = $db->getDatabaseName();
        return $db->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function ensureIndex(string $table, string $index, array $columns): void
    {
        if ($this->hasIndex($table, $index)) {
            return;
        }
        Schema::connection('priyasa')->table($table, function (Blueprint $t) use ($columns, $index) {
            $t->index($columns, $index);
        });
    }

    public function up(): void
    {
        $products = 'priyasa_products';
        $collections = 'priyasa_collection_product';

        // This migration can run after the later storefront SEO migration on an
        // existing database, so every column/index is added idempotently.
        Schema::connection('priyasa')->table($products, function (Blueprint $t) {
            if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'search_keywords')) {
                $t->text('search_keywords')->nullable();
            }
            if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'merchandising_score')) {
                $t->decimal('merchandising_score', 8, 3)->default(0);
            }
            if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'sort_order')) {
                $t->unsignedInteger('sort_order')->default(0);
            }
            if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'is_featured')) {
                $t->boolean('is_featured')->default(false);
            }
            if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'badge')) {
                $t->string('badge', 80)->nullable();
            }
            if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'meta_title')) {
                $t->string('meta_title', 255)->nullable();
            }
            if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'meta_description')) {
                $t->text('meta_description')->nullable();
            }
            if (!Schema::connection('priyasa')->hasColumn('priyasa_products', 'canonical_url')) {
                $t->string('canonical_url', 2048)->nullable();
            }
        });

        if (!$this->hasColumn($collections, 'sort_order')) {
            Schema::connection('priyasa')->table($collections, function (Blueprint $t) {
                $t->unsignedInteger('sort_order')->default(0);
            });
        }

        $this->ensureIndex($products, 'priyasa_products_is_featured_status_sort_order_index', ['is_featured', 'status', 'sort_order']);
        $this->ensureIndex($products, 'priyasa_products_brand_status_index', ['brand', 'status']);
        $this->ensureIndex($products, 'priyasa_products_merchandising_score_status_index', ['merchandising_score', 'status']);
        $this->ensureIndex($collections, 'priyasa_collection_product_collection_id_sort_order_index', ['collection_id', 'sort_order']);
    }

    public function down(): void
    {
        // Forward-compatible rollback: only remove columns/indexes owned by this
        // migration when they are present. SEO columns may be owned by the later
        // storefront SEO migration, so do not destructively remove them here.
        foreach ([
            'priyasa_products_is_featured_status_sort_order_index',
            'priyasa_products_brand_status_index',
            'priyasa_products_merchandising_score_status_index',
            'priyasa_collection_product_collection_id_sort_order_index',
        ] as $index) {
            $table = str_starts_with($index, 'priyasa_collection_product_') ? 'priyasa_collection_product' : 'priyasa_products';
            if ($this->hasIndex($table, $index)) {
                Schema::connection('priyasa')->table($table, fn (Blueprint $t) => $t->dropIndex($index));
            }
        }

        foreach (['search_keywords', 'merchandising_score', 'sort_order', 'is_featured', 'badge'] as $column) {
            if ($this->hasColumn('priyasa_products', $column)) {
                Schema::connection('priyasa')->table('priyasa_products', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }

        if ($this->hasColumn('priyasa_collection_product', 'sort_order')) {
            Schema::connection('priyasa')->table('priyasa_collection_product', fn (Blueprint $t) => $t->dropColumn('sort_order'));
        }
    }
};
