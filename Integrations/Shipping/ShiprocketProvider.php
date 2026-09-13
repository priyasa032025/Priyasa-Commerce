<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Integrations\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\PriyasaCore\Contracts\ShippingProvider;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\Shipment;
use RuntimeException;

final class ShiprocketProvider implements ShippingProvider
{
    private function token(): string { return Cache::remember('priyasa:shiprocket:token',now()->addHours(8),function(){ $r=Http::acceptJson()->timeout(15)->post('https://apiv2.shiprocket.in/v1/external/auth/login',['email'=>config('priyasacore.shiprocket.email'),'password'=>config('priyasacore.shiprocket.password')]); $r->throw(); $token=(string)$r->json('token'); if($token==='')throw new RuntimeException('Shiprocket authentication returned no token.'); return $token;}); }
    private function client(){return Http::withToken($this->token())->acceptJson()->timeout(20)->retry(2,300);}
    public function serviceability(string $pincode,string $paymentMode='prepaid'): array { $r=$this->client()->get('https://apiv2.shiprocket.in/v1/external/courier/serviceability',['pickup_postcode'=>config('priyasacore.shiprocket.pickup_pincode'),'delivery_postcode'=>$pincode,'cod'=>$paymentMode==='cod'?1:0,'weight'=>config('priyasacore.shiprocket.weight_kg')]);$r->throw();return $r->json(); }
    public function createShipment(Order $order): array {
        $order->loadMissing(['customer','shippingAddress','items.variant.product','shipment']);
        if($order->shipment?->shipment_reference) return $order->shipment->toArray();
        $address=$order->shippingAddress; if(!$address) throw new RuntimeException('Shipping address is required.');
        $items=$order->items->map(fn($i)=>['name'=>$i->product_name,'sku'=>$i->sku,'units'=>(int)$i->quantity,'selling_price'=>(float)$i->unit_price,'discount'=>0,'tax'=>0,'hsn'=>null])->values()->all();
        $r=$this->client()->post('https://apiv2.shiprocket.in/v1/external/orders/create/adhoc',[
            'order_id'=>$order->order_number,'order_date'=>$order->created_at?->format('Y-m-d H:i'),'pickup_location'=>config('priyasacore.shiprocket.pickup_location'),'channel_id'=>config('priyasacore.shiprocket.channel_id'),
            'billing_customer_name'=>$address->recipient_name,'billing_last_name'=>'','billing_address'=>$address->line1,'billing_address_2'=>$address->line2,'billing_city'=>$address->city,'billing_pincode'=>$address->postal_code,'billing_state'=>$address->state,'billing_country'=>strtoupper((string)$address->country) === 'IN' ? 'India' : ($address->country ?: 'India'),'billing_email'=>$order->customer?->email ?: 'orders@priyasa.com','billing_phone'=>$address->phone,
            'shipping_is_billing'=>true,'shipping_customer_name'=>$address->recipient_name,'shipping_last_name'=>'','shipping_address'=>$address->line1,'shipping_address_2'=>$address->line2,'shipping_city'=>$address->city,'shipping_pincode'=>$address->postal_code,'shipping_country'=>strtoupper((string)$address->country) === 'IN' ? 'India' : ($address->country ?: 'India'),'shipping_state'=>$address->state,'shipping_email'=>$order->customer?->email ?: 'orders@priyasa.com','shipping_phone'=>$address->phone,
            'order_items'=>$items,'payment_method'=>strtolower((string)$order->payment_method)==='cod'?'COD':'Prepaid','sub_total'=>(float)$order->subtotal,'length'=>(float)config('priyasacore.shiprocket.length_cm'),'breadth'=>(float)config('priyasacore.shiprocket.breadth_cm'),'height'=>(float)config('priyasacore.shiprocket.height_cm'),'weight'=>(float)config('priyasacore.shiprocket.weight_kg'),
        ]); $r->throw(); $data=$r->json(); $shipmentId=(string)($data['shipment_id']??''); $reference=(string)($data['order_id']??$order->order_number); $awb=null;
        if($shipmentId!==''){ $a=$this->client()->post('https://apiv2.shiprocket.in/v1/external/courier/assign/awb',['shipment_id'=>(int)$shipmentId]); $a->throw(); $ad=$a->json(); $awb=(string)($ad['response']['data']['awb_code']??$ad['awb_code']??''); $reference=$reference ?: (string)$shipmentId; }
        return Shipment::updateOrCreate(['order_id'=>$order->id],['provider'=>'shiprocket','shipment_reference'=>$reference?:null,'awb'=>$awb?:null,'status'=>$awb?'assigned':'created','tracking_url'=>$awb?'https://shiprocket.co/tracking/'.$awb:null,'metadata'=>array_merge($data,['shiprocket_shipment_id'=>$shipmentId,'shiprocket_order_id'=>$reference])])->toArray();
    }
    public function assignAwb(Shipment $shipment): array
    {
        if ($shipment->awb) return $shipment->fresh()->toArray();
        if (!$shipment->shipment_reference) throw new RuntimeException('Shipment reference is missing.');
        $shipmentId = (string) (($shipment->metadata['shiprocket_shipment_id'] ?? ''));
        if ($shipmentId === '' || !ctype_digit($shipmentId)) throw new RuntimeException('Shiprocket shipment ID is missing.');
        $response = $this->client()->post('https://apiv2.shiprocket.in/v1/external/courier/assign/awb', ['shipment_id' => (int) $shipmentId]);
        $response->throw();
        $data = $response->json();
        $awb = (string) ($data['response']['data']['awb_code'] ?? $data['awb_code'] ?? '');
        if ($awb === '') throw new RuntimeException('Shiprocket did not return an AWB.');
        $shipment->update(['awb'=>$awb,'status'=>'assigned','tracking_url'=>'https://shiprocket.co/tracking/'.$awb,'metadata'=>array_merge((array)$shipment->metadata,['awb_response'=>$data])]);
        return $shipment->fresh()->toArray();
    }

    public function cancelShipment(string $shipmentReference): array { $r=$this->client()->post('https://apiv2.shiprocket.in/v1/external/orders/cancel',['ids'=>[(int)$shipmentReference]]);$r->throw();return $r->json(); }
    public function track(string $trackingNumber): array { $r=$this->client()->get('https://apiv2.shiprocket.in/v1/external/courier/track/awb/'.rawurlencode($trackingNumber));$r->throw();return $r->json(); }
}
