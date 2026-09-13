<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Jobs;
use Illuminate\Bus\Queueable;use Illuminate\Contracts\Queue\ShouldQueue;use Illuminate\Foundation\Bus\Dispatchable;use Illuminate\Queue\InteractsWithQueue;use Illuminate\Queue\SerializesModels;use Modules\PriyasaCore\Services\Shipping\ShipmentOrchestrationService;
final class SyncShippingShipments implements ShouldQueue {use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;public function handle(ShipmentOrchestrationService $service):void{foreach(\DB::connection('priyasa')->table('priyasa_shipments')->whereIn('status',['assigned','label_generated','pickup_scheduled','shipped','out_for_delivery','ndr'])->whereNotNull('provider_shipment_id')->limit(200)->pluck('id') as $id){try{$service->sync((int)$id);}catch(\Throwable $e){report($e);}}}}
