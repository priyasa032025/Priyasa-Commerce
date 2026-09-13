<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\Payment;
use Modules\PriyasaCore\Models\PaymentTransaction;
use Modules\PriyasaCore\Models\WebhookEvent;
use Modules\PriyasaCore\Services\OrderService;

class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=5; public int $backoff=10;
    public function __construct(public int $eventId){}
    public function handle():void
    {
        $event=WebhookEvent::findOrFail($this->eventId); if($event->status==='processed') return;
        DB::connection('priyasa')->transaction(function() use($event){
            $event=WebhookEvent::query()->lockForUpdate()->findOrFail($event->id); if($event->status==='processed') return;
            try {
                if($event->provider==='razorpay') $this->handleRazorpay($event);
                elseif($event->provider==='woocommerce') { $woo=app(\Modules\PriyasaCore\Services\WooCommerceService::class); $topic=(string)$event->event_type; if(str_starts_with($topic,'order.')) $woo->upsertOrder($event->payload); elseif(str_starts_with($topic,'product.') && !empty($event->payload['id'])) $woo->syncProductById((int)$event->payload['id']); }
                $event->update(['status'=>'processed','processed_at'=>now(),'error'=>null]);
            } catch(\Throwable $e) { $event->update(['status'=>'failed','error'=>substr($e->getMessage(),0,65000)]); throw $e; }
        });
    }
    private function handleRazorpay(WebhookEvent $event): void
    {
        $p=$event->payload; $entity=$p['payload']['payment']['entity'] ?? $p['payload']['order']['entity'] ?? [];
        $paymentId=(string)($entity['id']??''); $orderId=(string)($entity['order_id']??'');
        if($paymentId==='' && $orderId==='') return;
        $payment=Payment::query()->where(function($q)use($paymentId,$orderId){ if($paymentId!=='')$q->where('provider_payment_id',$paymentId); if($orderId!=='')$q->orWhere('provider_order_id',$orderId); })->lockForUpdate()->first();
        if(!$payment) return;
        $order=$payment->order()->lockForUpdate()->with('items')->firstOrFail();
        if(in_array($event->event_type,['payment.captured','order.paid'],true)) {
            $amount=(int)($entity['amount']??0); $currency=(string)($entity['currency']??'');
            if($amount !== (int)round((float)$payment->amount*100) || ($currency !== '' && $currency !== (string)$payment->currency)) throw new \RuntimeException('Webhook payment amount or currency mismatch.');
            if($payment->status!=='captured') $payment->update(['provider_payment_id'=>$paymentId?:$payment->provider_payment_id,'status'=>'captured','captured_at'=>now(),'payload'=>array_merge($payment->payload??[],$p)]);
            if(!PaymentTransaction::where('type','capture')->where('provider_reference',$paymentId)->exists()) PaymentTransaction::create(['order_id'=>$order->id,'payment_id'=>$payment->id,'provider'=>$payment->provider,'type'=>'capture','provider_reference'=>$paymentId,'amount_paise'=>(int)round((float)$payment->amount*100),'status'=>'success','payload'=>$p]);
            if($order->status==='pending_payment') app(OrderService::class)->transition($order,'confirmed','webhook',null,'Razorpay payment confirmed');
        } elseif($event->event_type==='payment.failed' && $order->status==='pending_payment') {
            $payment->update(['status'=>'failed','provider_payment_id'=>$paymentId?:$payment->provider_payment_id,'payload'=>array_merge($payment->payload??[],$p)]);
            app(OrderService::class)->transition($order,'payment_failed','webhook',null,'Razorpay payment failed');
        }
    }
}
