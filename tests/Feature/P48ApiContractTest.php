<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class P48ApiContractTest extends TestCase
{
    private function source(string $file): string
    {
        $src = (string) file_get_contents($file);
        return preg_replace('~/\*.*?\*/|//[^\r\n]*|#[^\r\n]*~s', '', $src) ?? $src;
    }

    public function test_route_patches_do_not_introduce_nested_api_v1_prefixes(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];
        foreach (glob($root.'/routes_p*.patch') as $file) {
            $src = $this->source($file);
            if (preg_match("~Route::(?:get|post|put|patch|delete|options|match|any)\\(\\s*['\"]/?api/v1/~i", $src)) {
                $violations[] = basename($file).' contains an absolute /api/v1 route; route patches must be relative to the application API group.';
            }
            if (preg_match("~Route::prefix\\(\\s*['\"]api/v1['\"]~i", $src)) {
                $violations[] = basename($file).' declares prefix(api/v1); the host application owns the /api/v1 prefix.';
            }
        }
        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_every_controller_action_in_route_patches_exists_in_the_patch_tree(): void
    {
        $root = dirname(__DIR__, 2);
        $missing = [];
        foreach (glob($root.'/routes_p*.patch') as $file) {
            $src = $this->source($file);
            preg_match_all('/\[\s*([\\A-Za-z0-9_]+Controller)::class\s*,\s*[\'\"]([A-Za-z0-9_]+)[\'\"]\s*\]/', $src, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $class = ltrim($m[1], '\\');
                $method = $m[2];
                if (!str_starts_with($class, 'Modules\\PriyasaCore\\Http\\Controllers\\')) continue;
                $relative = substr($class, strlen('Modules\\PriyasaCore\\Http\\Controllers\\'));
                $candidates = [
                    $root.'/Http/Controllers/'.str_replace('\\', '/', $relative).'.php',
                    $root.'/'.basename(str_replace('\\', '/', $relative)).'.php',
                ];
                $path = null;
                foreach ($candidates as $candidate) if (is_file($candidate)) { $path = $candidate; break; }
                if ($path === null) { $missing[] = basename($file).': missing '.$class; continue; }
                $controller = (string) file_get_contents($path);
                if (!preg_match('/(?:public|protected|private)?\s*function\s+'.preg_quote($method, '/').'\s*\(/', $controller)) {
                    $missing[] = basename($file).': missing '.$class.'::'.$method;
                }
            }
        }
        $this->assertSame([], $missing, implode(PHP_EOL, $missing));
    }
}
