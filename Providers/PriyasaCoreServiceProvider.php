<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\PriyasaCore\Http\Middleware\AdminAbility;
use Modules\PriyasaCore\Http\Middleware\AdminPermissionMiddleware;
use Modules\PriyasaCore\Http\Middleware\AdminPermissionV2;
use Modules\PriyasaCore\Http\Middleware\AdminSecurityMiddleware;
use Modules\PriyasaCore\Http\Middleware\ApiContractHeaders;
use Modules\PriyasaCore\Http\Middleware\ApiContractResponse;
use Modules\PriyasaCore\Http\Middleware\AuthRateLimit;
use Modules\PriyasaCore\Http\Middleware\IdempotencyV2;
use Modules\PriyasaCore\Http\Middleware\RequestCorrelation;
use Modules\PriyasaCore\Services\AdminRbacService;
use Modules\PriyasaCore\Services\AdminAuditService;
use Modules\PriyasaCore\Services\GatewayManager;
use Modules\PriyasaCore\Services\PaymentService;
use Modules\PriyasaCore\Services\PromotionService;
use Modules\PriyasaCore\Services\CheckoutService;
use Modules\PriyasaCore\Services\WishlistService;
use Modules\PriyasaCore\Services\ReturnService;

final class PriyasaCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Dedicated commerce database. This is intentionally registered here so
        // config:cache and all module services see the same named connection.
        Config::set('database.connections.priyasa', array_merge(
            (array) config('database.connections.mysql', []),
            [
                'host' => env('PRIYASA_DB_HOST', env('DB_HOST', '127.0.0.1')),
                'port' => env('PRIYASA_DB_PORT', env('DB_PORT', 3306)),
                'database' => env('PRIYASA_DB_DATABASE', 'priyasa_commerce'),
                'username' => env('PRIYASA_DB_USERNAME', env('DB_USERNAME')),
                'password' => env('PRIYASA_DB_PASSWORD', env('DB_PASSWORD')),
                'unix_socket' => env('PRIYASA_DB_SOCKET', env('DB_SOCKET', '')),
                'charset' => env('PRIYASA_DB_CHARSET', 'utf8mb4'),
                'collation' => env('PRIYASA_DB_COLLATION', 'utf8mb4_unicode_ci'),
                'prefix' => env('PRIYASA_DB_PREFIX', ''),
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ));
        $this->app->singleton(GatewayManager::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(PromotionService::class);
        $this->app->singleton(CheckoutService::class);
        $this->app->singleton(WishlistService::class);
        $this->app->singleton(ReturnService::class);
        $this->app->singleton(AdminRbacService::class);
        $this->app->singleton(AdminAuditService::class);
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'priyasacore');
        $this->mergeConfigFrom(__DIR__.'/../Config/priyasa_database.php', 'priyasacore.database');
    }

    public function boot(): void
    {
        $router = $this->app->make('router');
        $router->aliasMiddleware('priyasa.admin', AdminAbility::class);
        $router->aliasMiddleware('priyasa.admin.permission', AdminPermissionMiddleware::class);
        $router->aliasMiddleware('priyasa.admin.security', AdminSecurityMiddleware::class);
        $router->aliasMiddleware('priyasa.admin.permission.v2', AdminPermissionV2::class);
        $router->aliasMiddleware('idempotency', IdempotencyV2::class);
        $router->aliasMiddleware('request.correlation', RequestCorrelation::class);
        $router->aliasMiddleware('api.contract.headers', ApiContractHeaders::class);
        $router->aliasMiddleware('api.contract', ApiContractResponse::class);
        $router->aliasMiddleware('priyasa.auth.rate_limit', AuthRateLimit::class);

        // Migrations are deliberately NOT auto-loaded into Laravel's default
        // migration run. Use `php artisan priyasa:migrate` so the commerce
        // database has its own migration repository and lifecycle.
        $this->commands([
            \Modules\PriyasaCore\Console\ReleaseExpiredReservationsCommand::class,
            \Modules\PriyasaCore\Console\PublishOutboxCommand::class,
            \Modules\PriyasaCore\Console\Commands\BuildApiContract::class,
            \Modules\PriyasaCore\Console\Commands\CertifyProductionApi::class,
            \Modules\PriyasaCore\Console\Commands\PriyasaMigrateCommand::class,
            \Modules\PriyasaCore\Console\Commands\PriyasaDbHealthCommand::class,
        ]);

        /*if (! $this->app->routesAreCached()) {
            Route::middleware(['api', ApiContractHeaders::class, ApiContractResponse::class, RequestCorrelation::class])
                ->prefix('api')
                ->group(function (): void {
                    require __DIR__.'/../Routes/api.php';
                    Route::prefix('v1')->group(function (): void {
                        $featureRoutes = __DIR__.'/../Routes/api_features.php';
                        if (is_file($featureRoutes)) require $featureRoutes;
                    });
                });
        }*/
        
        if (! $this->app->routesAreCached()) {

    // Swagger / OpenAPI documentation
    Route::get('/api/docs', function () {
        return response()->file(
            __DIR__.'/../docs/swagger.html',
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    })->name('priyasa.docs.swagger.api');

    Route::get('/api/docs/openapi.yaml', function () {
        return response()->file(
            __DIR__.'/../docs/openapi.yaml',
            ['Content-Type' => 'application/yaml; charset=UTF-8']
        );
    })->name('priyasa.docs.openapi.api');

    // Compatibility aliases
    Route::get('/docs/swagger.html', function () {
        return response()->file(
            __DIR__.'/../docs/swagger.html',
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    })->name('priyasa.docs.swagger');

    Route::get('/docs/openapi.yaml', function () {
        return response()->file(
            __DIR__.'/../docs/openapi.yaml',
            ['Content-Type' => 'application/yaml; charset=UTF-8']
        );
    })->name('priyasa.docs.openapi');

    // Priyasa API routes
    Route::middleware([
        'api',
        ApiContractHeaders::class,
        ApiContractResponse::class,
        RequestCorrelation::class,
    ])
        ->prefix('api')
        ->group(function (): void {

            require __DIR__.'/../Routes/api.php';

            Route::prefix('v1')->group(function (): void {
                $featureRoutes = __DIR__.'/../Routes/api_features.php';

                if (is_file($featureRoutes)) {
                    require $featureRoutes;
                }
            });
        });
}
    }
}
