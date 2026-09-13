<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class P49CheckoutE2EContractTest extends TestCase
{
    public function test_authoritative_checkout_components_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'Services/CheckoutTransactionService.php',
            'Services/CheckoutService.php',
            'Services/InventoryReservationService.php',
            'Services/CheckoutReliabilityService.php',
            'Http/Controllers/Storefront/CheckoutTransactionController.php',
            'Database/Migrations/2026_09_13_000038_p49_checkout_transaction.php',
            'routes_p49.patch',
        ] as $file) self::assertFileExists($root.'/'.$file);

        $service = file_get_contents($root.'/Services/CheckoutTransactionService.php') ?: '';
        foreach (['CheckoutService', 'CheckoutExperienceService', 'Idempotency-Key', 'server', 'grand_total'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
    }

    public function test_checkout_route_is_canonical_and_controller_methods_exist(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes_p49.patch') ?: '';
        self::assertStringNotContainsString('/api/v1/', $routes);
        self::assertStringContainsString('/storefront/checkout/transaction/quote', $routes);
        self::assertStringContainsString('/storefront/checkout/transaction/place', $routes);
        $controller = file_get_contents($root.'/Http/Controllers/Storefront/CheckoutTransactionController.php') ?: '';
        self::assertMatchesRegularExpression('/function\s+quote\s*\(/', $controller);
        self::assertMatchesRegularExpression('/function\s+place\s*\(/', $controller);
    }
}
