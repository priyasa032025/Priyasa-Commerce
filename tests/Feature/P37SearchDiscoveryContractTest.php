<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class P37SearchDiscoveryContractTest extends TestCase
{
    public function test_contract_defines_search_endpoints(): void
    {
        $patch = file_get_contents(__DIR__.'/../../routes_p37.patch');
        $this->assertStringContainsString('/storefront/search', $patch);
        $this->assertStringContainsString('/storefront/search/suggestions', $patch);
        $this->assertStringContainsString('/storefront/search/trending', $patch);
    }
}
