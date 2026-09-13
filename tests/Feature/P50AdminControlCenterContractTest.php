<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class P50AdminControlCenterContractTest extends TestCase
{
    public function test_p50_controller_and_routes_have_contract(): void
    {
        $base=dirname(__DIR__,2);
        $controller=$base.'/Http/Controllers/Admin/AdminCommerceControlCenterController.php';
        $service=$base.'/Services/AdminCommerceControlCenterService.php';
        $routes=$base.'/routes_p50.patch';
        $this->assertFileExists($controller); $this->assertFileExists($service); $this->assertFileExists($routes);
        $c=file_get_contents($controller); $r=file_get_contents($routes);
        foreach(['overview','orders','inventory','operations'] as $m) $this->assertStringContainsString('function '.$m,$c);
        $this->assertStringNotContainsString("prefix('api/v1')",$r);
        $this->assertStringContainsString("prefix('admin/control-center')",$r);
        foreach(['/','/orders','/inventory','/operations'] as $path) $this->assertStringContainsString("Route::get('{$path}'",$r);
    }
}
