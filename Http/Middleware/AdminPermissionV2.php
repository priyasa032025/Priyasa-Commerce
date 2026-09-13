<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class AdminPermissionV2
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (!$user) abort(401, 'Authentication required.');

        // P18 RBAC is optional in legacy installations. When present, mutation
        // permissions fail closed; super_admin receives all permissions.
        if (DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_admin_user_roles') && DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_admin_roles')) {
            $userId = (string) $user->getAuthIdentifier();
            $allowed = DB::connection('priyasa')->table('priyasa_admin_user_roles as ur')
                ->join('priyasa_admin_roles as r', 'r.id', '=', 'ur.role_id')
                ->leftJoin('priyasa_admin_role_permissions as rp', 'rp.role_id', '=', 'r.id')
                ->leftJoin('priyasa_admin_permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('ur.user_id', $userId)
                ->where(function ($q) use ($permission) {
                    $q->where('r.name', 'super_admin')->orWhere('p.name', $permission);
                })->exists();
            if (!$allowed) abort(403, 'Admin permission denied.');
        }
        return $next($request);
    }
}
