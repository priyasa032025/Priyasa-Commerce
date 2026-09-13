<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\PriyasaCore\Models\Order;

final class ReverseLogisticsService
{
    public function createReturnShipment(int $returnId, ?string $provider = null): array
    {
        return DB::connection('priyasa')->transaction(function () use ($returnId, $provider) {
            $return = DB::connection('priyasa')->table('priyasa_returns')->where('id', $returnId)->lockForUpdate()->first();
            if (!$return) throw ValidationException::withMessages(['return' => 'Return not found.']);
            $orderId = (int)($return->order_id ?? 0);
            if (!$orderId) throw ValidationException::withMessages(['return' => 'Return is not linked to an order.']);
            $existing = DB::connection('priyasa')->table('priyasa_reverse_shipments')->where('return_id', $returnId)->whereNotIn('status', ['cancelled'])->first();
            if ($existing) return (array)$existing;
            $name = $provider ?: (string)config('p34_shipping.provider', 'shiprocket');
            $id = DB::connection('priyasa')->table('priyasa_reverse_shipments')->insertGetId([
                'return_id'=>$returnId,'order_id'=>$orderId,'provider'=>$name,'status'=>'requested','metadata'=>json_encode(['created_by'=>'reverse_logistics']),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            return (array)DB::connection('priyasa')->table('priyasa_reverse_shipments')->where('id',$id)->first();
        });
    }

    public function updateReverseStatus(int $id, string $status, ?string $reason = null): array
    {
        $allowed=['requested','pickup_scheduled','picked_up','in_transit','received','qc_pending','completed','failed','cancelled'];
        if (!in_array($status,$allowed,true)) throw ValidationException::withMessages(['status'=>'Unsupported reverse shipment status.']);
        return DB::connection('priyasa')->transaction(function() use ($id,$status,$reason) {
            $s=DB::connection('priyasa')->table('priyasa_reverse_shipments')->where('id',$id)->lockForUpdate()->first();
            if(!$s) throw ValidationException::withMessages(['shipment'=>'Reverse shipment not found.']);
            $updates=['status'=>$status,'updated_at'=>now()];
            if($reason) $updates['failure_reason']=$reason;
            if($status==='picked_up') $updates['picked_up_at']=now();
            if($status==='received') $updates['received_at']=now();
            if($status==='cancelled') $updates['cancelled_at']=now();
            DB::connection('priyasa')->table('priyasa_reverse_shipments')->where('id',$id)->update($updates);
            return (array)DB::connection('priyasa')->table('priyasa_reverse_shipments')->where('id',$id)->first();
        });
    }

    public function recordNdr(int $shipmentId, ?string $reason=null): array
    {
        return DB::connection('priyasa')->transaction(function() use($shipmentId,$reason){
            $shipment=DB::connection('priyasa')->table('priyasa_shipments')->where('id',$shipmentId)->lockForUpdate()->first();
            if(!$shipment) throw ValidationException::withMessages(['shipment'=>'Shipment not found.']);
            $open=DB::connection('priyasa')->table('priyasa_ndr_cases')->where('shipment_id',$shipmentId)->where('status','open')->lockForUpdate()->first();
            if($open){DB::connection('priyasa')->table('priyasa_ndr_cases')->where('id',$open->id)->update(['attempts'=>(int)$open->attempts+1,'reason'=>$reason,'updated_at'=>now()]);return (array)DB::connection('priyasa')->table('priyasa_ndr_cases')->where('id',$open->id)->first();}
            $id=DB::connection('priyasa')->table('priyasa_ndr_cases')->insertGetId(['shipment_id'=>$shipmentId,'reason'=>$reason,'status'=>'open','attempts'=>1,'created_at'=>now(),'updated_at'=>now()]);
            DB::connection('priyasa')->table('priyasa_shipments')->where('id',$shipmentId)->update(['status'=>'ndr','updated_at'=>now()]);
            return (array)DB::connection('priyasa')->table('priyasa_ndr_cases')->where('id',$id)->first();
        });
    }

    public function resolveNdr(int $caseId, string $action): array
    {
        $allowed=['retry','customer_confirmed','return_to_origin','cancel'];
        if(!in_array($action,$allowed,true)) throw ValidationException::withMessages(['action'=>'Unsupported NDR action.']);
        return DB::connection('priyasa')->transaction(function() use($caseId,$action){
            $c=DB::connection('priyasa')->table('priyasa_ndr_cases')->where('id',$caseId)->lockForUpdate()->first();
            if(!$c||$c->status!=='open') throw ValidationException::withMessages(['ndr'=>'NDR case is not open.']);
            $status=$action==='return_to_origin'?'rto':'resolved';
            DB::connection('priyasa')->table('priyasa_ndr_cases')->where('id',$caseId)->update(['status'=>$status,'customer_action'=>$action,'updated_at'=>now()]);
            if($action==='return_to_origin') DB::connection('priyasa')->table('priyasa_shipments')->where('id',$c->shipment_id)->update(['status'=>'rto','rto_at'=>now(),'updated_at'=>now()]);
            elseif($action==='cancel') DB::connection('priyasa')->table('priyasa_shipments')->where('id',$c->shipment_id)->update(['status'=>'cancelled','cancelled_at'=>now(),'updated_at'=>now()]);
            else DB::connection('priyasa')->table('priyasa_shipments')->where('id',$c->shipment_id)->update(['status'=>'shipped','updated_at'=>now()]);
            return (array)DB::connection('priyasa')->table('priyasa_ndr_cases')->where('id',$caseId)->first();
        });
    }

    public function qc(int $returnId, string $status, bool $restock=false, bool $damaged=false, ?string $notes=null): array
    {
        if(!in_array($status,['passed','failed','partial'],true)) throw ValidationException::withMessages(['status'=>'QC status must be passed, partial, or failed.']);
        return DB::connection('priyasa')->transaction(function() use($returnId,$status,$restock,$damaged,$notes){
            if(!DB::connection('priyasa')->table('priyasa_returns')->where('id',$returnId)->exists()) throw ValidationException::withMessages(['return'=>'Return not found.']);
            $id=DB::connection('priyasa')->table('priyasa_return_qc')->insertGetId(['return_id'=>$returnId,'status'=>$status,'restock'=>$restock,'damaged'=>$damaged,'notes'=>$notes,'created_at'=>now(),'updated_at'=>now()]);
            if($status==='passed' || $status==='partial') DB::connection('priyasa')->table('priyasa_returns')->where('id',$returnId)->update(['status'=>'approved','updated_at'=>now()]);
            else DB::connection('priyasa')->table('priyasa_returns')->where('id',$returnId)->update(['status'=>'rejected','updated_at'=>now()]);
            return (array)DB::connection('priyasa')->table('priyasa_return_qc')->where('id',$id)->first();
        });
    }

    public function exchangeRequest(int $returnId): array
    {
        return DB::connection('priyasa')->transaction(function() use($returnId){
            $r=DB::connection('priyasa')->table('priyasa_returns')->where('id',$returnId)->lockForUpdate()->first();
            if(!$r) throw ValidationException::withMessages(['return'=>'Return not found.']);
            $existing=DB::connection('priyasa')->table('priyasa_exchange_orders')->where('return_id',$returnId)->first();
            if($existing) return (array)$existing;
            $id=DB::connection('priyasa')->table('priyasa_exchange_orders')->insertGetId(['return_id'=>$returnId,'order_id'=>(int)$r->order_id,'status'=>'requested','created_at'=>now(),'updated_at'=>now()]);
            return (array)DB::connection('priyasa')->table('priyasa_exchange_orders')->where('id',$id)->first();
        });
    }
}
