<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Services\AdminAuditService;
use Modules\PriyasaCore\Services\AdminRbacService;

final class AdminSecurityController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $u=$request->user(); $rbac=app(AdminRbacService::class);
        return response()->json(['data'=>['user_id'=>$u->id,'roles'=>$rbac->roles((int)$u->id),'permissions'=>$rbac->permissions((int)$u->id)]]);
    }
    public function roles(): JsonResponse { return response()->json(['data'=>DB::connection('priyasa')->table('priyasa_admin_roles')->where('is_active',true)->orderBy('display_name')->get()]); }
    public function permissions(): JsonResponse { return response()->json(['data'=>DB::connection('priyasa')->table('priyasa_admin_permissions')->orderBy('module')->orderBy('name')->get()]); }
    public function users(Request $request): JsonResponse
    {
        $q=DB::connection(config('database.default'))->table('users')->select('users.id','users.name','users.email','users.phone')->orderBy('users.id');
        if($request->filled('search')){ $t='%'.$request->input('search').'%'; $q->where(fn($w)=>$w->where('name','like',$t)->orWhere('email','like',$t)->orWhere('phone','like',$t)); }
        return response()->json(['data'=>$q->paginate(min(max((int)$request->input('per_page',25),1),100))]);
    }
    public function assignRole(Request $request, int $user, int $role): JsonResponse
    {
        $request->validate(['reason'=>'nullable|string|max:500']);
        app(AdminRbacService::class)->assignRole($user,$role,(int)$request->user()->id);
        app(AdminAuditService::class)->record((int)$request->user()->id,'rbac.role.assigned',['resource_type'=>'user','resource_id'=>$user,'after'=>['role_id'=>$role],'metadata'=>['reason'=>$request->input('reason')]]);
        return response()->json(['data'=>['user_id'=>$user,'role_id'=>$role,'assigned'=>true]]);
    }
    public function revokeRole(Request $request, int $user, int $role): JsonResponse
    {
        app(AdminRbacService::class)->revokeRole($user,$role);
        app(AdminAuditService::class)->record((int)$request->user()->id,'rbac.role.revoked',['resource_type'=>'user','resource_id'=>$user,'before'=>['role_id'=>$role]]);
        return response()->json(['data'=>['user_id'=>$user,'role_id'=>$role,'revoked'=>true]]);
    }
    public function audit(Request $request): JsonResponse
    {
        $q=DB::connection('priyasa')->table('priyasa_admin_audit_logs')->latest('id');
        foreach(['action','user_id','resource_type','resource_id','request_id'] as $f) if($request->filled($f)) $q->where($f,$request->input($f));
        return response()->json(['data'=>$q->paginate(min(max((int)$request->input('per_page',50),1),200))]);
    }
}
