<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Tests\Feature;
use PHPUnit\Framework\TestCase;

final class P47ApiRouteContractTest extends TestCase
{
    public function test_every_route_patch_points_to_an_existing_controller_method(): void
    {
        $root=dirname(__DIR__,2); $missing=[];
        foreach(glob($root.'/routes_p*.patch') as $file){
            $src=(string)file_get_contents($file);
            preg_match_all('/\[\s*([\\A-Za-z0-9_]+Controller)::class\s*,\s*[\'\"]([A-Za-z0-9_]+)[\'\"]\s*\]/',$src,$matches,PREG_SET_ORDER);
            foreach($matches as $m){
                $class='\\'.ltrim($m[1],'\\'); $method=$m[2]; $path=$root.'/'.str_replace('\\','/',$class).'.php';
                if(!is_file($path)) { $missing[]="$file: missing $class"; continue; }
                $controller=(string)file_get_contents($path);
                if(!preg_match('/function\s+'.preg_quote($method,'/').'\s*\(/',$controller)) $missing[]="$file: missing $class::$method";
            }
        }
        $this->assertSame([], $missing, "Route/controller contract failures:\n".implode("\n",$missing));
    }
}
