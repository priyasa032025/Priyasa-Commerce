<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PriyasaCore\Models\AdminRole;
use RuntimeException;

final class AdminUserController extends Controller
{
    private function userModel(): string
    {
        return (string) config('auth.providers.users.model', 'App\\Models\\User');
    }

    public function index(Request $request)
    {
        $model = $this->userModel();
        $query = $model::query()->orderByDesc('id');
        if ($search = trim((string)$request->query('search'))) {
            $query->where(fn($q)=>$q->where('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%"));
        }
        return response()->json(['success'=>true,'data'=>$query->paginate(min(max((int)$request->query('per_page',25),1),100))]);
    }

    public function roles()
    {
        return response()->json(['success'=>true,'data'=>AdminRole::query()->withCount('permissions')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255|unique:users,email','role'=>'required|in:super_admin,admin','status'=>'nullable|in:active,approved,pending']);
        $model=$this->userModel();
        $user=$model::create(array_merge($data,['password'=>Str::random(64)]));
        $role=AdminRole::where('slug',$data['role'])->first();
        if($role) {
            DB::connection('priyasa')->table('priyasa_admin_user_role')->insertOrIgnore(['admin_user_id'=>$user->getKey(),'role_id'=>$role->id]);
            if (DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_admin_user_roles')) DB::connection('priyasa')->table('priyasa_admin_user_roles')->insertOrIgnore(['user_id'=>$user->getKey(),'role_id'=>$role->id,'assigned_at'=>now()]);
        }
        return response()->json(['success'=>true,'data'=>$user->fresh()],201);
    }

    public function update(Request $request, int $admin)
    {
        $model=$this->userModel(); $user=$model::findOrFail($admin);
        $data=$request->validate(['name'=>'sometimes|string|max:255','email'=>'sometimes|email|max:255|unique:users,email,'.$user->getKey(),'role'=>'sometimes|in:super_admin,admin','status'=>'sometimes|in:active,approved,pending']);
        $user->fill($data)->save();
        if(isset($data['role'])) {
            $role=AdminRole::where('slug',$data['role'])->firstOrFail();
            DB::connection('priyasa')->table('priyasa_admin_user_role')->where('admin_user_id',$user->getKey())->delete();
            DB::connection('priyasa')->table('priyasa_admin_user_role')->insert(['admin_user_id'=>$user->getKey(),'role_id'=>$role->id]);
            if (DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_admin_user_roles')) {
                DB::connection('priyasa')->table('priyasa_admin_user_roles')->where('user_id',$user->getKey())->delete();
                DB::connection('priyasa')->table('priyasa_admin_user_roles')->insert(['user_id'=>$user->getKey(),'role_id'=>$role->id,'assigned_at'=>now()]);
            }
        }
        return response()->json(['success'=>true,'data'=>$user->fresh()]);
    }

    public function status(Request $request, int $admin)
    {
        $model=$this->userModel(); $user=$model::findOrFail($admin);
        $data=$request->validate(['status'=>'required|in:active,inactive']);
        $user->forceFill(['status'=>$data['status']])->save();
        return response()->json(['success'=>true,'data'=>$user->fresh()]);
    }
}
