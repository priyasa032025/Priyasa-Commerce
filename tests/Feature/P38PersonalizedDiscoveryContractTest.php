<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class P38PersonalizedDiscoveryContractTest extends TestCase
{
    public function test_contract_examples_are_documented(): void
    {
        $this->assertStringContainsString('/recommendations/home', file_get_contents(__DIR__.'/../../P38_PERSONALIZED_DISCOVERY.md') ?: '');
        $this->assertStringContainsString('/recommendations?', file_get_contents(__DIR__.'/../../P38_PERSONALIZED_DISCOVERY.md') ?: '');
    }
}
