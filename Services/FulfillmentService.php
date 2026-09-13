<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\OrderItem;
use Modules\PriyasaCore\Models\Shipment;
use Modules\PriyasaCore\Models\ShipmentEvent;
use RuntimeException;

final class FulfillmentService
{
    private const TERMINAL = ['delivered','cancelled','rto'];
    private const TRANSITIONS = [
        'pending'=>['created','cancelled'], 'created'=>['assigned','cancelled'], 'assigned'=>['packed','pickup_pending','cancelled'],
        'packed'=>['pickup_pending','cancelled'], 'pickup_pending'=>['picked_up','cancelled'], 'picked_up'=>['in_transit','ndr','cancelled'],
        'in_transit'=>['out_for_delivery','ndr','rto','delivered'], 'out_for_delivery'=>['delivered','ndr','rto'],
        'ndr'=>['out_for_delivery','rto','cancelled'], 'rto'=>['rto_received'], 'rto_received'=>['cancelled'],
        'delivered'=>[], 'cancelled'=>[]
    ];

    public function allocate(Order $order, array $allocations, ?string $provider = null): Shipment
    {
        return DB::connection('priyasa')->transaction(function () use ($order, $allocations, $provider) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (!in_array($order->status, ['confirmed','processing','packed'], true)) throw new RuntimeException('Order is not ready for fulfillment.');
            $items = OrderItem::query()->where('order_id',$order->id)->lockForUpdate()->get()->keyBy('id');
            if (!$items->count()) throw new RuntimeException('Order has no items.');
            $allocated = DB::connection('priyasa')->table('priyasa_shipment_items')->join('priyasa_shipments','priyasa_shipments.id','=','priyasa_shipment_items.shipment_id')->where('priyasa_shipments.order_id',$order->id)->select('order_item_id',DB::connection('priyasa')->raw('SUM(quantity) total'))->groupBy('order_item_id')->pluck('total','order_item_id');
            foreach ($allocations as $a) {
                $itemId=(int)($a['order_item_id']??0); $qty=(int)($a['quantity']??0);
                if (!$items->has($itemId) || $qty < 1) throw new RuntimeException('Invalid shipment item allocation.');
                $remaining=(int)$items[$itemId]->quantity-(int)($allocated[$itemId]??0);
                if ($qty>$remaining) throw new RuntimeException("Shipment quantity exceeds remaining quantity for order item {$itemId}.");
            }
            $shipment=Shipment::create(['order_id'=>$order->id,'provider'=>$provider ?: (string)config('priyasacore.shipping.provider','shiprocket'),'shipment_reference'=>'PRIYASA-'.Str::upper(Str::random(12)),'status'=>'created','metadata'=>['allocation_source'=>'fulfillment']]);
            foreach ($allocations as $a) $shipment->items()->create(['order_item_id'=>(int)$a['order_item_id'],'quantity'=>(int)$a['quantity']]);
            $this->record($shipment,'created','created','Shipment allocated');
            return $shipment->load('items.orderItem');
        });
    }

    public function transition(Shipment $shipment, string $status, array $payload=[]): Shipment
    {
        return DB::connection('priyasa')->transaction(function() use($shipment,$status,$payload){
            $shipment=Shipment::query()->lockForUpdate()->findOrFail($shipment->id); $from=$shipment->status;
            if (!array_key_exists($status,self::TRANSITIONS) || !in_array($status,self::TRANSITIONS[$from]??[],true)) { if($from!==$status) throw new RuntimeException("Invalid shipment transition {$from} -> {$status}."); }
            $now=now(); $changes=['status'=>$status,'last_synced_at'=>$now,'metadata'=>array_merge((array)$shipment->metadata,['last_transition'=>$payload])];
            if($status==='picked_up'||$status==='in_transit'||$status==='out_for_delivery') $changes['shipped_at']=$shipment->shipped_at ?: $now;
            if($status==='delivered') $changes['delivered_at']=$now;
            if($status==='cancelled'||$status==='rto') $changes['cancelled_at']=$now;
            $shipment->update($changes); $this->record($shipment,$status,$payload['code']??null,$payload['description']??null,$payload); return $shipment->fresh(['items.orderItem','events']);
        });
    }
    public function record(Shipment $shipment,string $status,?string $code=null,?string $description=null,array $payload=[]): void { ShipmentEvent::firstOrCreate(['shipment_id'=>$shipment->id,'code'=>$code,'occurred_at'=>$payload['occurred_at']??now()],['status'=>$status,'location'=>$payload['location']??null,'description'=>$description,'payload'=>$payload]); }
    public function allocationSummary(Order $order): array
    {
        $items=$order->items()->get()->map(function($i){$allocated=(int)DB::connection('priyasa')->table('priyasa_shipment_items')->join('priyasa_shipments','priyasa_shipments.id','=','priyasa_shipment_items.shipment_id')->where('priyasa_shipments.order_id',$i->order_id)->where('order_item_id',$i->id)->sum('quantity'); return ['order_item_id'=>$i->id,'ordered'=>(int)$i->quantity,'allocated'=>$allocated,'remaining'=>max(0,(int)$i->quantity-$allocated)];})->values();
        return ['complete'=>$items->every(fn($x)=>$x['remaining']===0),'items'=>$items];
    }
}
