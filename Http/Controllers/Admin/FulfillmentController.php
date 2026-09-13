<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\Shipment;
use Modules\PriyasaCore\Services\FulfillmentService;
use RuntimeException;
final class FulfillmentController extends Controller {
 public function summary(Order $order,FulfillmentService $service){return response()->json(['success'=>true,'data'=>$service->allocationSummary($order)]);}
 public function allocate(Request $request,Order $order,FulfillmentService $service){$data=$request->validate(['provider'=>'nullable|string|max:40','items'=>'required|array|min:1','items.*.order_item_id'=>'required|integer','items.*.quantity'=>'required|integer|min:1']); return response()->json(['success'=>true,'data'=>$service->allocate($order,$data['items'],$data['provider']??null)],201);}
 public function transition(Request $request,Shipment $shipment,FulfillmentService $service){$data=$request->validate(['status'=>'required|string|max:50','code'=>'nullable|string|max:80','location'=>'nullable|string|max:191','description'=>'nullable|string','occurred_at'=>'nullable|date','payload'=>'nullable|array']); return response()->json(['success'=>true,'data'=>$service->transition($shipment,$data['status'],array_merge($data['payload']??[],$data))]);}
 public function show(Shipment $shipment){return response()->json(['success'=>true,'data'=>$shipment->load(['order.customer','items.orderItem','events'=>fn($q)=>$q->latest('occurred_at')])]);}
 public function index(Request $request){$q=Shipment::query()->with('order')->latest('id'); if($s=trim((string)$request->query('status'))) $q->where('status',$s); if($p=trim((string)$request->query('provider'))) $q->where('provider',$p); return response()->json(['success'=>true,'data'=>$q->paginate(min(max((int)$request->query('per_page',25),1),100))]);}
}
