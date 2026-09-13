<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Tests;

use Modules\PriyasaCore\Providers\PriyasaCoreServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [PriyasaCoreServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('priyasacore.razorpay.key_secret', 'test-secret');
    }
}
