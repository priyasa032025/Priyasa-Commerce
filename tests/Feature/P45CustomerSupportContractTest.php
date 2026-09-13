<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\PriyasaCore\Services\CustomerSupportService;
use PHPUnit\Framework\TestCase;

final class P45CustomerSupportContractTest extends TestCase
{
    public function test_categories_are_stable(): void
    {
        $items = (new CustomerSupportService())->categories();
        self::assertContains('order',$items);
        self::assertContains('refund',$items);
        self::assertContains('account',$items);
    }
}
