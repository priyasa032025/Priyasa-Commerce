<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Services\WarehouseInventoryService;

final class WarehouseInventoryController extends Controller
{
 public function warehouses(){return response()->json(['success'=>true,'data'=>DB::connection('priyasa')->table('priyasa_warehouses')->orderBy('name')->get()]);}
 public function inventory(Request $r){$q=DB::connection('priyasa')->table('priyasa_warehouse_inventory as wi')->join('priyasa_warehouses as w','w.id','=','wi.warehouse_id')->select('wi.*','w.code as warehouse_code','w.name as warehouse_name'); if($r->filled('warehouse_id'))$q->where('wi.warehouse_id',$r->integer('warehouse_id')); if($r->filled('variant_id'))$q->where('wi.variant_id',$r->integer('variant_id')); return response()->json(['success'=>true,'data'=>$q->orderBy('wi.id','desc')->paginate(min($r->integer('per_page',50),200))]);}
 public function adjust(Request $r, WarehouseInventoryService $service){$data=$r->validate(['warehouse_id'=>'required|integer','variant_id'=>'required|integer','delta'=>'required|integer|not_in:0','reason'=>'nullable|string|max:100','reference_type'=>'nullable|string|max:100','reference_id'=>'nullable|string|max:100']); return response()->json(['success'=>true,'data'=>$service->adjust($data['warehouse_id'],$data['variant_id'],$data['delta'],$data['reason']??'manual',$data['reference_type']??null,$data['reference_id']??null)]);}
}
