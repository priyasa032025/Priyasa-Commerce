<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAbility
{
    public function handle(Request $request, Closure $next, string $ability = 'admin')
    {
        $user = $request->user();
        abort_unless($user, 401, 'Authentication required.');

        foreach (['is_admin', 'is_super_admin', 'is_staff'] as $flag) {
            if ((bool) data_get($user, $flag, false)) {
                return $next($request);
            }
        }

        // Prefer the production RBAC tables when available.
        try {
            if (DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_admin_user_roles')) {
                $allowed = DB::connection('priyasa')->table('priyasa_admin_user_roles as ur')
                    ->join('priyasa_admin_roles as r', 'r.id', '=', 'ur.role_id')
                    ->where('ur.user_id', $user->getAuthIdentifier())
                    ->where(function ($q): void {
                        if (DB::connection('priyasa')->getSchemaBuilder()->hasColumn('priyasa_admin_roles', 'is_active')) {
                            $q->where('r.is_active', true);
                        }
                    })->exists();
                if ($allowed) return $next($request);
            }
        } catch (\Throwable) {
            // Fall through to the host application's Gate/Can implementation.
        }

        if (method_exists($user, 'can') && $user->can($ability)) {
            return $next($request);
        }

        abort(403, 'Admin permission required.');
    }
}
