<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\Shipping\ShipmentOrchestrationService;
final class ShippingOrchestrationController extends Controller {
 public function create(Request $r, Order $order, ShipmentOrchestrationService $s){$r->validate(['provider'=>'nullable|string|max:40']);return response()->json(['success'=>true,'data'=>$s->createFromPackedAllocations($order,$r->input('provider'))]);}
 public function show(Order $order, ShipmentOrchestrationService $s){return response()->json(['success'=>true,'data'=>$s->showOrder($order)]);}
 public function label(int $shipment, ShipmentOrchestrationService $s){return response()->json(['success'=>true,'data'=>$s->label($shipment)]);}
 public function pickup(int $shipment, ShipmentOrchestrationService $s){return response()->json(['success'=>true,'data'=>$s->pickup($shipment)]);}
 public function sync(int $shipment, ShipmentOrchestrationService $s){return response()->json(['success'=>true,'data'=>$s->sync($shipment)]);}
 public function cancel(int $shipment, ShipmentOrchestrationService $s){return response()->json(['success'=>true,'data'=>$s->cancel($shipment)]);}
}
