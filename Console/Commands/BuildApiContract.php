<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class BuildApiContract extends Command
{
    protected $signature = 'priyasa:api-contract {--output= : Output OpenAPI JSON path}';
    protected $description = 'Build an OpenAPI route inventory from the application route table.';

    public function handle(): int
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn($r) => Str::startsWith('/'.$r->uri(), '/api/v1'))
            ->map(function ($r) {
                return [
                    'method' => implode('|', array_values(array_diff($r->methods(), ['HEAD']))),
                    'uri' => '/'.$r->uri(),
                    'name' => $r->getName(),
                    'action' => $r->getActionName(),
                    'middleware' => $r->gatherMiddleware(),
                ];
            })->values()->all();

        $output = $this->option('output') ?: base_path('docs/openapi.routes.json');
        $dir = dirname($output);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        file_put_contents($output, json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'PRIYASA Commerce API Route Contract', 'version' => 'v1'],
            'routes' => $routes,
        ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
        $this->info('Wrote '.count($routes).' API routes to '.$output);
        return self::SUCCESS;
    }
}
