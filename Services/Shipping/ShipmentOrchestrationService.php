<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services\Shipping;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\PriyasaCore\Models\Order;
use RuntimeException;

final class ShipmentOrchestrationService
{
    public function __construct(private ShippingProviderManager $providers) {}

    public function createFromPackedAllocations(Order $order, ?string $provider=null): array
    {
        return DB::connection('priyasa')->transaction(function() use ($order,$provider) {
            $allocations=DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('order_id',$order->id)->where('status','packed')->lockForUpdate()->get();
            if($allocations->isEmpty()) throw ValidationException::withMessages(['fulfillment'=>'No packed allocations are available.']);
            $created=[];
            foreach($allocations->groupBy('warehouse_id') as $warehouseId=>$rows){
                $existing=DB::connection('priyasa')->table('priyasa_shipments')->where('order_id',$order->id)->where('warehouse_id',$warehouseId)->whereNotIn('status',['cancelled'])->first();
                if($existing){$created[]=(array)$existing;continue;}
                $warehouse=DB::connection('priyasa')->table('priyasa_warehouses')->where('id',$warehouseId)->first(); if(!$warehouse) throw new RuntimeException('Fulfillment warehouse not found.');
                $items=[]; foreach($rows as $row){$item=DB::connection('priyasa')->table('priyasa_order_items')->where('id',$row->order_item_id)->first();$items[]=['order_item_id'=>(int)$row->order_item_id,'variant_id'=>(int)$row->variant_id,'quantity'=>(int)$row->quantity,'sku'=>$item->sku ?? null,'name'=>$item->product_name ?? null,'unit_price'=>(float)($item->unit_price ?? 0)];}
                $providerName=$provider ?: (string)config('p34_shipping.provider','shiprocket');
                $payload=$this->providerPayload($order,$warehouse,$items,$order->order_number.'-'.$warehouse->code);
                $result=$this->providers->driver($providerName)->createShipment($payload);
                $providerShipmentId=$this->providerShipmentId($result);
                $awb=(string)($result['awb_code'] ?? $result['response']['data']['awb_code'] ?? '');
                $shipmentId=DB::connection('priyasa')->table('priyasa_shipments')->insertGetId([
                    'order_id'=>$order->id,'warehouse_id'=>$warehouseId,'provider'=>$providerName,'provider_shipment_id'=>$providerShipmentId,
                    'shipment_reference'=>$result['order_id'] ?? ($order->order_number.'-'.$warehouse->code),'awb'=>$awb ?: null,'courier_name'=>$result['courier_name'] ?? null,
                    'status'=>$awb?'assigned':'created','tracking_url'=>$awb?'https://shiprocket.co/tracking/'.rawurlencode($awb):null,'metadata'=>json_encode($result),'created_at'=>now(),'updated_at'=>now()
                ]);
                foreach($items as $item){DB::connection('priyasa')->table('priyasa_shipment_items')->insert(['shipment_id'=>$shipmentId,'order_item_id'=>$item['order_item_id'],'quantity'=>$item['quantity'],'variant_id'=>$item['variant_id'],'created_at'=>now(),'updated_at'=>now()]);}
                foreach($rows as $row) DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('id',$row->id)->update(['status'=>'shipped','updated_at'=>now()]);
                $created[]=(array)DB::connection('priyasa')->table('priyasa_shipments')->where('id',$shipmentId)->first();
            }
            return ['order_id'=>$order->id,'shipments'=>$created];
        });
    }
    public function label(int $shipmentId): array { $s=$this->get($shipmentId);$r=$this->providers->driver($s->provider)->generateLabel((string)$s->provider_shipment_id);$label=$r['label_url'] ?? (is_array($r['label_url'] ?? null)?($r['label_url'][0]??null):null);DB::connection('priyasa')->table('priyasa_shipments')->where('id',$shipmentId)->update(['label_url'=>$label,'status'=>'label_generated','updated_at'=>now()]);return $this->result($shipmentId,$r); }
    public function pickup(int $shipmentId): array { $s=$this->get($shipmentId);if(!in_array($s->status,['created','assigned','label_generated','pickup_failed'],true))throw ValidationException::withMessages(['shipment'=>'Shipment is not ready for pickup.']);$r=$this->providers->driver($s->provider)->schedulePickup((string)$s->provider_shipment_id);DB::connection('priyasa')->table('priyasa_shipments')->where('id',$shipmentId)->update(['status'=>'pickup_scheduled','pickup_scheduled_at'=>now(),'updated_at'=>now()]);return $this->result($shipmentId,$r); }
    public function sync(int $shipmentId): array { $s=$this->get($shipmentId);$r=$this->providers->driver($s->provider)->track((string)($s->awb ?: $s->provider_shipment_id));$status=$this->normalizeStatus($r);$updates=['status'=>$status,'last_synced_at'=>now(),'updated_at'=>now()];if($status==='shipped')$updates['shipped_at']=now();if($status==='delivered')$updates['delivered_at']=now();if($status==='rto')$updates['rto_at']=now();if($status==='cancelled')$updates['cancelled_at']=now();DB::connection('priyasa')->table('priyasa_shipments')->where('id',$shipmentId)->update($updates);if(DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_shipment_events'))DB::connection('priyasa')->table('priyasa_shipment_events')->insert(['shipment_id'=>$shipmentId,'status'=>$status,'code'=>$this->eventCode($r),'location'=>$this->eventLocation($r),'description'=>$this->statusMessage($r),'occurred_at'=>now(),'payload'=>json_encode($r),'created_at'=>now(),'updated_at'=>now()]);return $this->result($shipmentId,$r); }
    public function cancel(int $shipmentId): array { $s=$this->get($shipmentId);if(in_array($s->status,['delivered','rto','cancelled'],true))throw ValidationException::withMessages(['shipment'=>'Shipment cannot be cancelled in its current state.']);$r=$this->providers->driver($s->provider)->cancel((string)$s->provider_shipment_id);DB::connection('priyasa')->table('priyasa_shipments')->where('id',$shipmentId)->update(['status'=>'cancelled','cancelled_at'=>now(),'updated_at'=>now()]);return $this->result($shipmentId,$r); }
    public function showOrder(Order $order): array { $rows=DB::connection('priyasa')->table('priyasa_shipments')->where('order_id',$order->id)->orderBy('id')->get();return ['order_id'=>$order->id,'shipments'=>$rows]; }
    private function get(int $id){$s=DB::connection('priyasa')->table('priyasa_shipments')->where('id',$id)->first();if(!$s)throw ValidationException::withMessages(['shipment'=>'Shipment not found.']);return $s;}
    private function result(int $id,array $provider): array{return ['shipment'=>(array)$this->get($id),'provider_response'=>$provider];}
    private function providerPayload($order,$warehouse,array $items,string $reference):array{$a=DB::connection('priyasa')->table('priyasa_addresses')->where('id',$order->shipping_address_id)->first();if(!$a)throw ValidationException::withMessages(['shipping_address'=>'Shipping address is required.']);$customer=DB::connection('priyasa')->table('priyasa_customers')->where('id',$order->customer_id)->first();return ['order_id'=>$reference,'order_date'=>$order->created_at?date('Y-m-d H:i',strtotime((string)$order->created_at)):date('Y-m-d H:i'),'pickup_location'=>$warehouse->code,'channel_id'=>config('p34_shipping.shiprocket.channel_id'),'billing_customer_name'=>$a->recipient_name,'billing_last_name'=>'','billing_address'=>$a->line1,'billing_address_2'=>$a->line2 ?: '','billing_city'=>$a->city,'billing_pincode'=>$a->postal_code,'billing_state'=>$a->state,'billing_country'=>'India','billing_email'=>$customer->email ?? 'orders@priyasa.com','billing_phone'=>$a->phone,'shipping_is_billing'=>true,'shipping_customer_name'=>$a->recipient_name,'shipping_last_name'=>'','shipping_address'=>$a->line1,'shipping_address_2'=>$a->line2 ?: '','shipping_city'=>$a->city,'shipping_pincode'=>$a->postal_code,'shipping_state'=>$a->state,'shipping_country'=>'India','shipping_email'=>$customer->email ?? 'orders@priyasa.com','shipping_phone'=>$a->phone,'order_items'=>array_map(fn($i)=>['name'=>$i['name'] ?: $i['sku'] ?: 'Item','sku'=>$i['sku'] ?: (string)$i['variant_id'],'units'=>$i['quantity'],'selling_price'=>$i['unit_price'],'discount'=>0,'tax'=>0,'hsn'=>null],$items),'payment_method'=>strtolower((string)$order->payment_method)==='cod'?'COD':'Prepaid','sub_total'=>(float)$order->subtotal,'length'=>(float)config('p34_shipping.shiprocket.default_length_cm',10),'breadth'=>(float)config('p34_shipping.shiprocket.default_breadth_cm',10),'height'=>(float)config('p34_shipping.shiprocket.default_height_cm',10),'weight'=>(float)config('p34_shipping.shiprocket.default_weight_kg',0.5)];}
    private function providerShipmentId(array $r):string{foreach(['shipment_id','id'] as $k)if(isset($r[$k])&&is_scalar($r[$k]))return(string)$r[$k];throw new RuntimeException('Shipping provider did not return a shipment id.');}
    private function normalizeStatus(array $r):string{$s=strtolower((string)($r['status']??$r['tracking_data']['shipment_status']??$r['tracking_data']['shipment_track'][0]['current_status']??''));return match(true){str_contains($s,'delivered')=>'delivered',str_contains($s,'rto')||str_contains($s,'return to origin')=>'rto',str_contains($s,'cancel')=>'cancelled',str_contains($s,'ndr')=>'ndr',str_contains($s,'out for delivery')||str_contains($s,'out_for_delivery')=>'out_for_delivery',str_contains($s,'picked')||str_contains($s,'shipped')||str_contains($s,'in transit')=>'shipped',str_contains($s,'packed')=>'packed',default=>'created'};}
    private function eventCode(array $r):?string{return isset($r['tracking_data']['shipment_track'][0]['current_status'])?(string)$r['tracking_data']['shipment_track'][0]['current_status']:null;}
    private function eventLocation(array $r):?string{return isset($r['tracking_data']['shipment_track'][0]['location'])?(string)$r['tracking_data']['shipment_track'][0]['location']:null;}
    private function statusMessage(array $r):?string{return isset($r['status'])?(string)$r['status']:(isset($r['tracking_data']['shipment_track'][0]['current_status'])?(string)$r['tracking_data']['shipment_track'][0]['current_status']:null);}
}
