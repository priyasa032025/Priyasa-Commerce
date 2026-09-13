<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class CertifyProductionApi extends Command
{
    protected $signature = 'priyasa:api-certify {--strict : Fail on any warning}';
    protected $description = 'Run non-destructive production API boot, route, schema and contract checks.';

    public function handle(): int
    {
        $fail = [];
        $warn = [];

        $provider = __DIR__.'/../../Providers/PriyasaCoreServiceProvider.php';
        if (!is_file($provider)) $fail[] = 'PriyasaCoreServiceProvider.php is missing.';

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with('/'.$route->uri(), '/api/v1'));
        if ($routes->isEmpty()) $fail[] = 'No /api/v1 routes are registered.';

        foreach ($routes as $route) {
            $uri = '/'.$route->uri();
            if (str_contains($uri, '/api/v1/api/v1/')) $fail[] = 'Nested API prefix: '.$uri;
        }

        foreach ([
            'priyasa_orders', 'priyasa_order_items', 'priyasa_products', 'priyasa_product_variants',
            'priyasa_customers', 'priyasa_inventory', 'priyasa_payments',
        ] as $table) {
            try {
                if (!Schema::connection('priyasa')->hasTable($table)) $fail[] = 'Required commerce table missing: '.$table;
            } catch (\Throwable $e) {
                $fail[] = 'Schema check failed for '.$table.': '.$e->getMessage();
            }
        }

        foreach ([
            '/api/v1/storefront/bootstrap',
            '/api/v1/storefront/account/me',
            '/api/v1/storefront/checkout/transaction/quote',
            '/api/v1/storefront/checkout/transaction/place',
            '/api/v1/admin/control-center',
            '/api/v1/admin/actions/orders/{order}/transition',
        ] as $required) {
            if (!$routes->contains(fn ($route) => '/'.$route->uri() === $required)) $fail[] = 'Required route missing: '.$required;
        }

        foreach (['priyasa.admin','priyasa.admin.permission','priyasa.admin.security','idempotency','api.contract'] as $alias) {
            try {
                if (!app('router')->getMiddleware()->has($alias)) $warn[] = 'Middleware alias not visible: '.$alias;
            } catch (\Throwable) {
                $warn[] = 'Unable to inspect middleware alias: '.$alias;
            }
        }

        try {
            DB::connection('priyasa')->select('select 1');
        } catch (\Throwable $e) {
            $fail[] = 'Database connectivity check failed: '.$e->getMessage();
        }

        $this->line('PRIYASA production API certification');
        $this->line('API routes: '.$routes->count());
        foreach ($fail as $item) $this->error('FAIL  '.$item);
        foreach ($warn as $item) $this->warn('WARN  '.$item);
        if (!$fail && (!$warn || !$this->option('strict'))) $this->info('CERTIFIED: application boot, API routes and core schema checks passed.');

        return $fail || ($this->option('strict') && $warn) ? self::FAILURE : self::SUCCESS;
    }
}
