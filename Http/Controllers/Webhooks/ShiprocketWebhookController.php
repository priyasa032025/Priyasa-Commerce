<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Webhooks;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Services\Shipping\ShipmentOrchestrationService;
final class ShiprocketWebhookController extends Controller {
 public function handle(Request $request, ShipmentOrchestrationService $service){$payload=$request->all();$awb=(string)($payload['awb']??$payload['awb_code']??$payload['tracking_data']['shipment_track'][0]['awb_code']??'');$shipment=$awb?DB::connection('priyasa')->table('priyasa_shipments')->where('awb',$awb)->first():null;if(!$shipment){$id=$payload['shipment_id']??null;$shipment=$id?DB::connection('priyasa')->table('priyasa_shipments')->where('provider_shipment_id',(string)$id)->first():null;}if(!$shipment)return response()->json(['success'=>true,'ignored'=>true]);try{$result=$service->sync((int)$shipment->id);return response()->json(['success'=>true,'data'=>$result]);}catch(\Throwable $e){report($e);return response()->json(['success'=>false,'error'=>['code'=>'SHIPMENT_SYNC_FAILED','message'=>'Shipment update could not be processed.']],500);}}
}
