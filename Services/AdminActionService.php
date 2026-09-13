<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\GatewayManager;
use Modules\PriyasaCore\Services\RefundService;
use Modules\PriyasaCore\Services\ReturnService;
use RuntimeException;

final class AdminActionService
{
    public function execute(string $key, string $action, string $permission, ?string $actorId, callable $callback, array $meta=[]): array
    {
        $hash = hash('sha256', json_encode(['action'=>$action,'meta'=>$meta], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        return DB::connection('priyasa')->transaction(function () use ($key,$action,$permission,$actorId,$callback,$meta,$hash) {
            $existing = DB::connection('priyasa')->table('priyasa_admin_action_idempotency')->where('idempotency_key',$key)->lockForUpdate()->first();
            if ($existing) {
                if ((string)$existing->request_hash !== $hash) throw ValidationException::withMessages(['Idempotency-Key'=>'Key was already used for a different request.']);
                return (array)json_decode((string)$existing->response, true);
            }
            $result = $callback();
            $payload = is_array($result) ? $result : ['result'=>$result];
            DB::connection('priyasa')->table('priyasa_admin_action_idempotency')->insert([
                'idempotency_key'=>$key,'action'=>$action,'actor_id'=>$actorId,'request_hash'=>$hash,
                'response'=>json_encode($payload),'status_code'=>200,'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::connection('priyasa')->table('priyasa_admin_action_audits')->insert([
                'actor_id'=>$actorId,'action'=>$action,'permission'=>$permission,'idempotency_key'=>$key,
                'result'=>'success','after'=>json_encode($payload),'metadata'=>json_encode($meta),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $payload;
        });
    }

    public function orderTransition(int $orderId, string $to, string $actorId, string $key, ?string $note=null): array
    {
        return $this->execute($key,'order.transition','orders.manage',$actorId,function() use($orderId,$to,$actorId,$note){
            $order=Order::query()->lockForUpdate()->findOrFail($orderId);
            $updated=app(OrderService::class)->transition($order,$to,'admin',$actorId,$note);
            return ['order'=>$updated->toArray(),'status'=>$updated->status];
        },['order_id'=>$orderId,'to'=>$to]);
    }

    public function inventoryAdjust(int $warehouseId,int $variantId,int $delta,string $reason,string $actorId,string $key): array
    {
        return $this->execute($key,'inventory.adjust','inventory.manage',$actorId,function() use($warehouseId,$variantId,$delta,$reason){
            return app(WarehouseInventoryService::class)->adjust($warehouseId,$variantId,$delta,$reason,'admin',null);
        },compact('warehouseId','variantId','delta','reason'));
    }

    public function warehouseTransfer(int $from,int $to,int $variant,int $quantity,?string $note,string $actorId,string $key): array
    {
        return $this->execute($key,'warehouse.transfer.create','inventory.manage',$actorId,function() use($from,$to,$variant,$quantity,$note){
            return app(FulfillmentAllocationService::class)->createTransfer($from,$to,$variant,$quantity,$note);
        },compact('from','to','variant','quantity','note'));
    }

    public function fulfillment(int $allocationId,string $operation,string $actorId,string $key): array
    {
        $permission='fulfillment.manage';
        return $this->execute($key,'fulfillment.'.$operation,$permission,$actorId,function() use($allocationId,$operation){
            $s=app(FulfillmentAllocationService::class);
            return match($operation){ 'pick'=>$s->markPicked($allocationId), 'pack'=>$s->markPacked($allocationId), 'cancel'=>$s->cancelAllocation($allocationId), default=>throw ValidationException::withMessages(['operation'=>'Unsupported fulfillment operation.']) };
        },compact('allocationId','operation'));
    }

    public function returnAction(int $returnId,string $operation,string $actorId,string $key): array
    {
        return $this->execute($key,'return.'.$operation,'returns.manage',$actorId,function() use($returnId,$operation){
            $request=\Modules\PriyasaCore\Models\ReturnRequest::query()->findOrFail($returnId);
            $target=match($operation){'approve'=>'approved','reject'=>'rejected','pickup'=>'picked_up','receive'=>'received','qc_pass'=>'qc_passed','qc_fail'=>'qc_failed','refund'=>'refunded','cancel'=>'cancelled',default=>null};
            if(!$target) throw ValidationException::withMessages(['operation'=>'Unsupported return operation.']);
            $updated=app(ReturnService::class)->transition($request,$target,'Admin command center');
            return $updated->toArray();
        },compact('returnId','operation'));
    }

    public function refundAction(int $refundId,int $amountPaise,string $actorId,string $key,?string $reason=null): array
    {
        return $this->execute($key,'refund.initiate','refunds.manage',$actorId,function() use($refundId,$amountPaise,$reason){
            $row=DB::connection('priyasa')->table('priyasa_refunds')->find($refundId);
            if(!$row) throw ValidationException::withMessages(['refund_id'=>'Refund not found.']);
            $order=Order::query()->findOrFail($row->order_id);
            $payment=$order->payment()->first();
            if(!$payment) throw ValidationException::withMessages(['payment'=>'Refund has no payment.']);
            $gateway=app(GatewayManager::class)->payment($payment->provider);
            return app(RefundService::class)->refund($order,$amountPaise,$gateway,$reason,$row->return_id)->toArray();
        },compact('refundId','amountPaise','reason'));
    }

}
