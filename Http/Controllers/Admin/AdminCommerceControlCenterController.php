<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\AdminCommerceControlCenterService;

final class AdminCommerceControlCenterController extends Controller
{
    public function overview(AdminCommerceControlCenterService $s)
    { return response()->json(['success'=>true,'type'=>'admin.control_center.overview','data'=>$s->overview(),'api_version'=>'v1']); }

    public function orders(Request $r, AdminCommerceControlCenterService $s)
    { return response()->json(['success'=>true,'type'=>'admin.control_center.orders','data'=>$s->orders($r->all()),'api_version'=>'v1']); }

    public function inventory(Request $r, AdminCommerceControlCenterService $s)
    { $d=$r->validate(['threshold'=>'nullable|integer|min:0|max:100000']); return response()->json(['success'=>true,'type'=>'admin.control_center.inventory','data'=>$s->inventory($d),'api_version'=>'v1']); }

    public function operations(AdminCommerceControlCenterService $s)
    { return response()->json(['success'=>true,'type'=>'admin.control_center.operations','data'=>$s->operations(),'api_version'=>'v1']); }
}
