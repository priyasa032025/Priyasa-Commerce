<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Runtime certification tests. These execute only inside the host Laravel application.
 * They deliberately avoid inventing factories or external credentials; project factories
 * and test credentials remain the application's responsibility.
 */
final class P52ProductionApiE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_api_route_inventory_is_registered_without_nested_api_prefix(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with('/'.$route->uri(), '/api/v1'));

        $this->assertGreaterThan(0, $routes->count(), 'No /api/v1 routes are registered.');
        foreach ($routes as $route) {
            $uri = '/'.$route->uri();
            $this->assertStringNotContainsString('/api/v1/api/v1/', $uri);
        }
    }

    public function test_storefront_bootstrap_has_the_canonical_contract(): void
    {
        $response = $this->getJson('/api/v1/storefront/bootstrap');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success', 'type', 'data', 'meta', 'errors', 'api_version',
        ]);
    }

    public function test_unauthenticated_account_api_is_rejected_consistently(): void
    {
        $response = $this->getJson('/api/v1/storefront/account/me');
        $response->assertStatus(401);
        $response->assertJsonStructure([
            'success', 'type', 'data', 'meta', 'errors', 'api_version',
        ]);
    }

    public function test_runtime_route_inventory_contains_the_core_production_surfaces(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => '/'.$route->uri())
            ->filter(fn ($uri) => str_starts_with($uri, '/api/v1'));

        foreach ([
            '/api/v1/storefront/bootstrap',
            '/api/v1/storefront/account/me',
            '/api/v1/storefront/checkout/transaction/quote',
            '/api/v1/storefront/checkout/transaction/place',
        ] as $required) {
            $this->assertTrue($uris->contains($required), 'Missing runtime API route: '.$required);
        }
    }
}
