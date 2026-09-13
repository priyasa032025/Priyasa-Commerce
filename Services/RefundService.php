<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Contracts\PaymentGateway;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\Refund;
use Modules\PriyasaCore\Models\PaymentTransaction;
use RuntimeException;
final class RefundService {
 public function refund(Order $order, int $amountPaise, PaymentGateway $gateway, ?string $reason=null, ?int $returnId=null): Refund {
  return DB::connection('priyasa')->transaction(function() use($order,$amountPaise,$gateway,$reason){
   $order=Order::query()->lockForUpdate()->findOrFail($order->id); $payment=$order->payment()->lockForUpdate()->first();
   if(!$payment || $payment->status!=='captured' || !$payment->provider_payment_id) throw new RuntimeException('Only captured online payments can be refunded.');
   $amount=round($amountPaise/100,2); $remaining=round((float)$payment->amount-(float)$payment->refunded_amount,2);
   if($amount<=0 || $amount>$remaining) throw new RuntimeException('Refund amount exceeds refundable amount.');
   $existing=Refund::where('order_id',$order->id)->where('status','success')->sum('amount');
   if(round((float)$existing+$amount,2)>round((float)$payment->amount,2)) throw new RuntimeException('Refund exceeds captured payment.');
   $remote=$gateway->refund((string)$payment->provider_payment_id,$amountPaise,$reason);
   $refund=Refund::create(['order_id'=>$order->id,'payment_id'=>$payment->id,'return_id'=>$returnId,'provider'=>$payment->provider,'provider_refund_id'=>$remote['id']??null,'amount'=>$amount,'currency'=>$payment->currency,'status'=>'success','reason'=>$reason,'payload'=>$remote,'processed_at'=>now()]);
   PaymentTransaction::create(['order_id'=>$order->id,'payment_id'=>$payment->id,'return_id'=>$returnId,'provider'=>$payment->provider,'provider_reference'=>$remote['id']??null,'type'=>'refund','amount_paise'=>$amountPaise,'status'=>'success','payload'=>$remote,'refund_id'=>$refund->id]);
   $new=round((float)$payment->refunded_amount+$amount,2); $payment->refunded_amount=$new; $payment->refunded_at=now(); if($new >= (float)$payment->amount) $payment->status='refunded'; $payment->save();
   if($new >= (float)$payment->amount && $order->status==='returned') app(OrderService::class)->transition($order,'refunded','system',null,'Payment fully refunded');
   return $refund->fresh();
  });
 }
}
