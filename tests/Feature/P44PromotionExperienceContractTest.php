<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Tests\Feature;

use Tests\TestCase;

final class P44PromotionExperienceContractTest extends TestCase
{
    public function test_contract_documentation_exists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 3).'/P44_PROMOTION_LOYALTY_EXPERIENCE.md');
    }
}
