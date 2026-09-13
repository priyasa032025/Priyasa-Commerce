<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\ReverseLogisticsService;
final class ReverseLogisticsController extends Controller {
 public function createReturnShipment(Request $r,int $return,ReverseLogisticsService $s){$r->validate(['provider'=>'nullable|string|max:40']);return response()->json(['success'=>true,'data'=>$s->createReturnShipment($return,$r->input('provider'))]);}
 public function reverseStatus(Request $r,int $shipment,ReverseLogisticsService $s){$r->validate(['status'=>'required|string','reason'=>'nullable|string|max:1000']);return response()->json(['success'=>true,'data'=>$s->updateReverseStatus($shipment,$r->string('status')->toString(),$r->input('reason'))]);}
 public function ndr(Request $r,int $shipment,ReverseLogisticsService $s){$r->validate(['reason'=>'nullable|string|max:120']);return response()->json(['success'=>true,'data'=>$s->recordNdr($shipment,$r->input('reason'))]);}
 public function resolveNdr(Request $r,int $case,ReverseLogisticsService $s){$r->validate(['action'=>'required|string']);return response()->json(['success'=>true,'data'=>$s->resolveNdr($case,$r->string('action')->toString())]);}
 public function qc(Request $r,int $return,ReverseLogisticsService $s){$r->validate(['status'=>'required|string','restock'=>'boolean','damaged'=>'boolean','notes'=>'nullable|string|max:2000']);return response()->json(['success'=>true,'data'=>$s->qc($return,$r->string('status')->toString(),$r->boolean('restock'),$r->boolean('damaged'),$r->input('notes'))]);}
 public function exchange(int $return,ReverseLogisticsService $s){return response()->json(['success'=>true,'data'=>$s->exchangeRequest($return)]);}
}
