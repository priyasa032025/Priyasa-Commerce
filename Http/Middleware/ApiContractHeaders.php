<?php
namespace Modules\PriyasaCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ApiContractHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $requestId = (string)($request->header('X-Request-Id') ?: Str::uuid());
        $response->headers->set('X-Priyasa-API-Version','v1');
        $response->headers->set('X-Priyasa-Contract-Version','1.1');
        $response->headers->set('X-Request-Id',$requestId);
        $response->headers->set('Vary','Accept, Authorization, X-Session-Id');
        return $response;
    }
}
