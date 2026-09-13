<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Integrations\Shipping\ShiprocketProvider;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\Shipment;
use RuntimeException;

final class ShippingController extends Controller
{
    public function index(Request $request)
    {
        $q = Shipment::query()->with(['order.customer'])->latest('id');
        if ($search = trim((string) $request->query('search'))) {
            $q->where(function ($x) use ($search) {
                $x->where('awb', 'like', "%{$search}%")
                    ->orWhere('shipment_reference', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"));
            });
        }
        if ($status = trim((string) $request->query('status'))) $q->where('status', $status);
        return response()->json(['success'=>true,'data'=>$q->paginate(min(max((int)$request->query('per_page',25),1),100))]);
    }

    public function create(Request $request, Order $order, ShiprocketProvider $provider)
    {
        if ($order->shipment()->exists()) return response()->json(['success'=>true,'data'=>$order->shipment]);
        if (!in_array($order->status, ['confirmed','processing','packed'], true)) {
            throw new RuntimeException('Order is not ready for shipment creation.');
        }
        return response()->json(['success'=>true,'data'=>$provider->createShipment($order)], 201);
    }

    public function awb(Shipment $shipment, ShiprocketProvider $provider)
    {
        if ($shipment->awb) return response()->json(['success'=>true,'data'=>$shipment->fresh()]);
        $updated = $provider->assignAwb($shipment);
        return response()->json(['success'=>true,'data'=>$updated]);
    }

    public function track(Shipment $shipment, ShiprocketProvider $provider)
    {
        if (!$shipment->awb) return response()->json(['success'=>false,'message'=>'Shipment has no AWB.'],422);
        $tracking = $provider->track($shipment->awb);
        $remoteStatus=(string)($tracking['tracking_data']['shipment_status']??$shipment->status);
        $mapped=$this->mapStatus($remoteStatus);
        $shipment->update(['metadata'=>array_merge((array)$shipment->metadata,['tracking'=>$tracking]),'status'=>$mapped,'shipped_at'=>$mapped==='shipped'?($shipment->shipped_at?:now()):$shipment->shipped_at,'delivered_at'=>$mapped==='delivered'?now():$shipment->delivered_at]);
        if($mapped==='shipped' && in_array($shipment->order->status,['processing','packed'],true)) app(\Modules\PriyasaCore\Services\OrderService::class)->transition($shipment->order,'shipped','system',null,'Shipment tracking updated');
        if($mapped==='delivered' && $shipment->order->status==='out_for_delivery') app(\Modules\PriyasaCore\Services\OrderService::class)->transition($shipment->order,'delivered','system',null,'Shipment delivered');
        return response()->json(['success'=>true,'data'=>$shipment->fresh()]);
    }

    private function mapStatus(string $status): string {
        $s=strtolower(trim($status));
        return match(true) { str_contains($s,'delivered') => 'delivered', str_contains($s,'out for delivery') || str_contains($s,'out-for-delivery') => 'out_for_delivery', str_contains($s,'transit') || str_contains($s,'shipped') => 'in_transit', str_contains($s,'cancel') => 'cancelled', default => 'assigned' };
    }

    public function cancel(Shipment $shipment, ShiprocketProvider $provider)
    {
        if (!$shipment->shipment_reference) return response()->json(['success'=>false,'message'=>'Shipment reference is missing.'],422);
        $result = $provider->cancelShipment($shipment->shipment_reference);
        $shipment->update(['status'=>'cancelled','metadata'=>array_merge((array)$shipment->metadata,['cancel_response'=>$result])]);
        return response()->json(['success'=>true,'data'=>$shipment->fresh()]);
    }
}
