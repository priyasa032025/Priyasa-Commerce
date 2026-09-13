<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class P40CheckoutExperienceContractTest extends TestCase
{
    public function test_contract_documents_required_checkout_surface(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes_p40.patch');
        $this->assertStringContainsString('/checkout/addresses', $routes);
        $this->assertStringContainsString('/checkout/delivery', $routes);
        $this->assertStringContainsString('/checkout/quote', $routes);
        $this->assertStringContainsString('/checkout/place', $routes);
    }
}
