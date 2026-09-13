<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class P51AdminActionsContractTest extends TestCase
{
    public function test_admin_action_contract_is_present(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes_p51.patch');
        $controller = file_get_contents($root.'/Http/Controllers/Admin/AdminCommerceActionsController.php');
        self::assertStringContainsString("prefix('admin/actions')", $routes);
        foreach (['orderTransition','inventoryAdjust','transfer','fulfillment','returnAction','refundAction'] as $method) {
            self::assertStringContainsString('function '.$method, $controller);
        }
        self::assertStringContainsString("Idempotency-Key", $controller);
        self::assertStringContainsString('priyasa_admin_action_idempotency', file_get_contents($root.'/Database/Migrations/2026_09_13_000039_p51_admin_actions.php'));
    }
}
