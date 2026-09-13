<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminRbacService
{
    public function hasPermission(?int $userId, string $permission): bool
    {
        if (!$userId || !Schema::connection('priyasa')->hasTable('priyasa_admin_user_roles')) return false;
        return DB::connection('priyasa')->table('priyasa_admin_user_roles as ur')
            ->join('priyasa_admin_roles as r','r.id','=','ur.role_id')
            ->join('priyasa_admin_role_permissions as rp','rp.role_id','=','r.id')
            ->join('priyasa_admin_permissions as p','p.id','=','rp.permission_id')
            ->where('ur.user_id',$userId)->where('r.is_active',true)->where('p.name',$permission)->exists();
    }

    public function roles(int $userId): array
    {
        return DB::connection('priyasa')->table('priyasa_admin_user_roles as ur')->join('priyasa_admin_roles as r','r.id','=','ur.role_id')
            ->where('ur.user_id',$userId)->where('r.is_active',true)->pluck('r.name')->values()->all();
    }

    public function permissions(int $userId): array
    {
        return DB::connection('priyasa')->table('priyasa_admin_user_roles as ur')->join('priyasa_admin_roles as r','r.id','=','ur.role_id')
            ->join('priyasa_admin_role_permissions as rp','rp.role_id','=','r.id')->join('priyasa_admin_permissions as p','p.id','=','rp.permission_id')
            ->where('ur.user_id',$userId)->where('r.is_active',true)->distinct()->pluck('p.name')->values()->all();
    }

    public function assignRole(int $userId, int $roleId, ?int $assignedBy = null): void
    {
        DB::connection('priyasa')->table('priyasa_admin_user_roles')->updateOrInsert(['user_id'=>$userId,'role_id'=>$roleId], ['assigned_by'=>$assignedBy,'assigned_at'=>now()]);
    }

    public function revokeRole(int $userId, int $roleId): void
    {
        DB::connection('priyasa')->table('priyasa_admin_user_roles')->where('user_id',$userId)->where('role_id',$roleId)->delete();
    }
}
