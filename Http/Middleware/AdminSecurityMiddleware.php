<?php
namespace Modules\PriyasaCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\AdminAuditService;

final class AdminSecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response=$next($request);
        $user=$request->user();
        app(AdminAuditService::class)->record($user ? (int)$user->id : null,'admin.request.completed',[
            'method'=>$request->method(),'route'=>optional($request->route())->uri(),'request_id'=>$request->header('X-Request-ID'),
            'ip_address'=>$request->ip(),'user_agent'=>substr((string)$request->userAgent(),0,1000),
            'metadata'=>['status'=>$response->getStatusCode()],
        ]);
        return $response;
    }
}
