<?php
namespace Modules\PriyasaCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class AuthRateLimit
{
    public function handle(Request $request, Closure $next, string $bucket='auth')
    {
        $key = 'priyasa:'.$bucket.':'.sha1(implode('|', [
            (string)$request->ip(),
            strtolower((string)($request->input('phone') ?: $request->input('mobile') ?: '')),
            strtolower((string)$request->input('email')),
        ]));
        $max = (int)config('priyasacore.auth.rate_limits.'.$bucket.'.max', 5);
        $seconds = (int)config('priyasacore.auth.rate_limits.'.$bucket.'.decay_seconds', 60);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return response()->json(['success'=>false,'type'=>'error','data'=>null,'meta'=>['retry_after'=>RateLimiter::availableIn($key)],'errors'=>[['code'=>'RATE_LIMITED','message'=>'Too many authentication attempts.','details'=>[]]],'api_version'=>'v1'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        RateLimiter::hit($key, $seconds);
        return $next($request);
    }
}
