<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\FulfillmentAllocationService;
use Illuminate\Support\Facades\DB;
final class FulfillmentAllocationController extends Controller {
 public function allocate(Request $r, Order $order, FulfillmentAllocationService $s){$d=$r->validate(['pincode'=>'nullable|string|max:20']); return response()->json(['success'=>true,'data'=>$s->allocateOrder($order,$d['pincode']??null)]);}
 public function show(Order $order,FulfillmentAllocationService $s){return response()->json(['success'=>true,'data'=>$s->summary($order)]);}
 public function pick(int $allocation,FulfillmentAllocationService $s){return response()->json(['success'=>true,'data'=>$s->markPicked($allocation)]);}
 public function pack(int $allocation,FulfillmentAllocationService $s){return response()->json(['success'=>true,'data'=>$s->markPacked($allocation)]);}
 public function cancel(int $allocation,FulfillmentAllocationService $s){return response()->json(['success'=>true,'data'=>$s->cancelAllocation($allocation)]);}
 public function transfer(Request $r,FulfillmentAllocationService $s){$d=$r->validate(['from_warehouse_id'=>'required|integer','to_warehouse_id'=>'required|integer','variant_id'=>'required|integer','quantity'=>'required|integer|min:1','note'=>'nullable|string|max:1000']);return response()->json(['success'=>true,'data'=>$s->createTransfer($d['from_warehouse_id'],$d['to_warehouse_id'],$d['variant_id'],$d['quantity'],$d['note']??null)]);}
 public function receiveTransfer(Request $r,int $transfer,FulfillmentAllocationService $s){$d=$r->validate(['received_quantity'=>'required|integer|min:1']);return response()->json(['success'=>true,'data'=>$s->receiveTransfer($transfer,$d['received_quantity'])]);}
 public function transfers(Request $r){$q=DB::connection('priyasa')->table('priyasa_warehouse_transfers')->orderByDesc('id'); if($r->filled('status'))$q->where('status',$r->string('status')); return response()->json(['success'=>true,'data'=>$q->paginate(min($r->integer('per_page',50),200))]);}
}
