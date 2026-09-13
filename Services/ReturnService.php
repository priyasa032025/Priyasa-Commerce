<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\ReturnRequest;
use Modules\PriyasaCore\Models\PaymentTransaction;
use Modules\PriyasaCore\Models\ReturnItem;
use Modules\PriyasaCore\Models\OrderItem;
use RuntimeException;
use Modules\PriyasaCore\Services\GatewayManager;
use Modules\PriyasaCore\Services\RefundService;

final class ReturnService
{
    public function request(Customer $customer, Order $order, array $data): ReturnRequest
    {
        if ((int)$order->customer_id !== (int)$customer->id) throw new RuntimeException('Order does not belong to customer.');
        if ($order->status !== 'delivered') throw new RuntimeException('Order is not eligible for return.');
        if ($order->returns()->whereIn('status',['requested','approved','picked_up','received','qc_passed'])->exists()) throw new RuntimeException('A return is already in progress.');

        return DB::connection('priyasa')->transaction(function () use ($customer, $order, $data): ReturnRequest {
            $request = ReturnRequest::create([
                'order_id'=>$order->id,
                'customer_id'=>$customer->id,
                'reason'=>$data['reason'],
                'status'=>'requested',
                'metadata'=>$data['metadata'] ?? null,
                'customer_note'=>$data['customer_note'] ?? null,
            ]);
            $requestedItems=(array)($data['items'] ?? []);
            if ($requestedItems===[]) $requestedItems=array_map(fn($i)=>['order_item_id'=>$i->id,'quantity'=>(int)$i->quantity], $order->items()->get()->all());
            foreach($requestedItems as $ri){
                $item=OrderItem::query()->where('order_id',$order->id)->findOrFail((int)$ri['order_item_id']);
                $qty=(int)$ri['quantity']; if($qty<1 || $qty>(int)$item->quantity) throw new RuntimeException('Invalid return quantity.');
                ReturnItem::create(['return_id'=>$request->id,'order_item_id'=>$item->id,'quantity'=>$qty,'refund_amount'=>round(((float)$item->line_total/(int)$item->quantity)*$qty,2),'reason'=>$data['reason']]);
            }
            app(OrderService::class)->transition($order, 'return_requested', 'customer', (string)$customer->id, 'Return requested');
            return $request->fresh('items');
        });
    }

    public function transition(ReturnRequest $request, string $status, ?string $note = null): ReturnRequest
    {
        $allowed=['requested'=>['approved','rejected'],'approved'=>['picked_up','cancelled'],'picked_up'=>['received'],'received'=>['qc_passed','qc_failed'],'qc_passed'=>['refunded'],'qc_failed'=>['rejected'],'refunded'=>[],'rejected'=>[],'cancelled'=>[]];
        if (!in_array($status,$allowed[$request->status]??[],true)) throw new RuntimeException('Invalid return transition.');

        return DB::connection('priyasa')->transaction(function () use ($request,$status,$note): ReturnRequest {
            $request=ReturnRequest::query()->lockForUpdate()->findOrFail($request->id);
            $allowed=['requested'=>['approved','rejected'],'approved'=>['picked_up','cancelled'],'picked_up'=>['received'],'received'=>['qc_passed','qc_failed'],'qc_passed'=>['refunded'],'qc_failed'=>['rejected'],'refunded'=>[],'rejected'=>[],'cancelled'=>[]];
            if (!in_array($status,$allowed[$request->status]??[],true)) throw new RuntimeException('Invalid return transition.');
            $order=Order::query()->lockForUpdate()->findOrFail($request->order_id);
            if ($status==='qc_passed') app(InventoryService::class)->restockReturn($request);
            if ($status==='refunded' && $order->status==='returned') {
                $payment=$order->payment;
                if ($payment && $payment->status==='captured') {
                    $amount=(float)$request->items()->sum('refund_amount');
                    $already=(float)PaymentTransaction::query()->where('order_id',$order->id)->where('type','refund')->where('status','success')->sum(DB::connection('priyasa')->raw('amount_paise / 100'));
                    $remaining=max(0,round((float)$payment->amount-$already,2));
                    $amount=min($amount,$remaining);
                    if($amount>0) app(RefundService::class)->refund($order,(int)round($amount*100),app(GatewayManager::class)->payment($payment->provider),'Return refund',(int)$request->id);
                }
            }
            $request->status=$status; $request->admin_note=$note; $request->save();
            if ($status==='refunded' && $order->status==='returned') app(OrderService::class)->transition($order,'refunded','admin',null,'Return refunded');
            if ($status==='qc_passed' && $order->status==='return_requested') app(OrderService::class)->transition($order,'returned','admin',null,'Return QC passed');
            return $request->fresh('items');
        });
    }
}
