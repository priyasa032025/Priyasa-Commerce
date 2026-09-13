<?php
namespace Modules\PriyasaCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\AdminRbacService;

final class AdminPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message'=>'Unauthenticated'],401);
        $ok = app(AdminRbacService::class)->hasPermission((int)$user->id, $permission);
        if (!$ok && (bool)config('priyasacore.admin.legacy_permission_fallback', true) && $request->user()->can('priyasa.admin')) $ok = true;
        if (!$ok) return response()->json(['message'=>'Forbidden','permission'=>$permission],403);
        return $next($request);
    }
}
