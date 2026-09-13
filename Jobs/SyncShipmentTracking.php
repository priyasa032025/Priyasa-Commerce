<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Models\Shipment;
use Modules\PriyasaCore\Services\FulfillmentService;
use Modules\PriyasaCore\Integrations\Shipping\ShiprocketProvider;
final class SyncShipmentTracking implements ShouldQueue { use Dispatchable,InteractsWithQueue,Queueable,SerializesModels; public function __construct(public int $shipmentId){} public function handle(FulfillmentService $service,ShiprocketProvider $provider): void { $s=Shipment::find($this->shipmentId); if(!$s||!$s->awb||in_array($s->status,['delivered','cancelled','rto_received'],true)) return; $data=$provider->track($s->awb); $status=$this->map((string)($data['tracking_data']['shipment_status']??'')); if($status) { try{$service->transition($s,$status,['provider'=>'shiprocket','tracking'=>$data]);}catch(\Throwable $e){$s->update(['metadata'=>array_merge((array)$s->metadata,['tracking'=>$data,'transition_error'=>$e->getMessage()])]);} } else $s->update(['metadata'=>array_merge((array)$s->metadata,['tracking'=>$data]),'last_synced_at'=>now()]); }
 private function map(string $v):?string { $v=strtolower(trim($v)); return match(true){str_contains($v,'delivered')=>'delivered',str_contains($v,'out for delivery')=>'out_for_delivery',str_contains($v,'in transit')||str_contains($v,'shipped')=>'in_transit',str_contains($v,'picked')=>'picked_up',str_contains($v,'ndr')=>'ndr',str_contains($v,'rto')=>'rto',default=>null}; }
}
