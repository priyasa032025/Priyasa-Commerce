<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestId
{
    public function handle(Request $request, Closure $next)
    {
        $id = (string) ($request->header('X-Request-Id') ?: Str::uuid());
        $request->attributes->set('request_id', $id);
        $response = $next($request);
        return $response->header('X-Request-Id', $id);
    }
}
