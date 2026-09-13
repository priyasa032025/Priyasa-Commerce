<?php
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\{Order,OrderItem,ReturnRequest,ReturnItem};
use RuntimeException;
final class PostOrderLifecycleService {
 public function eligibility(Order $order): array {
  $window=(int)config('priyasacore.returns.return_window_days',7);
  $eligibleAt=$order->delivered_at ? $order->delivered_at->copy()->addDays($window) : null;
  $canReturn=$order->status==='delivered' && (!$eligibleAt || now()->lte($eligibleAt));
  $canCancel=in_array($order->status,['pending_payment','payment_failed','confirmed','processing','packed'],true);
  $items=[];
  foreach($order->items as $item){$returned=(int)ReturnItem::where('order_item_id',$item->id)->whereHas('returnRequest',fn($q)=>$q->whereNotIn('status',['rejected','cancelled']))->sum('quantity');$items[]=['order_item_id'=>$item->id,'ordered'=>(int)$item->quantity,'returned'=>$returned,'returnable'=>max(0,(int)$item->quantity-$returned)];}
  return ['can_cancel'=>$canCancel,'can_return'=>$canReturn,'return_window_ends_at'=>$eligibleAt?->toISOString(),'items'=>$items];
 }
 public function cancel(Order $order, string $actor='customer', ?string $actorId=null): Order {
  if(!in_array($order->status,['pending_payment','payment_failed','confirmed','processing','packed'],true)) throw new RuntimeException('Order is not cancellable in its current state.');
  return app(OrderService::class)->transition($order,'cancelled',$actor,$actorId,'Order cancelled');
 }
 public function requestReturn(Order $order, array $items, string $reason, string $actorId): ReturnRequest {
  return DB::connection('priyasa')->transaction(function() use($order,$items,$reason,$actorId){
   $elig=$this->eligibility($order); if(!$elig['can_return']) throw new RuntimeException('Order is outside the return window or not eligible.');
   $map=collect($elig['items'])->keyBy('order_item_id'); $total=0; $valid=[];
   foreach($items as $row){$id=(int)($row['order_item_id']??0);$qty=(int)($row['quantity']??0);$e=$map->get($id);if(!$e||$qty<1||$qty>$e['returnable']) throw new RuntimeException("Invalid return quantity for order item {$id}.");$oi=OrderItem::findOrFail($id);$amount=(float)$oi->unit_price*$qty;$total+=$amount;$valid[]=['order_item_id'=>$id,'quantity'=>$qty,'refund_amount'=>$amount];}
   if(!$valid) throw new RuntimeException('At least one return item is required.');
   $rr=ReturnRequest::create(['order_id'=>$order->id,'customer_id'=>$order->customer_id,'reason'=>$reason,'status'=>'requested','refund_amount'=>$total,'metadata'=>['actor_id'=>$actorId,'item_count'=>count($valid)]]);
   foreach($valid as $v) ReturnItem::create(['return_id'=>$rr->id]+$v);
   if($order->status==='delivered') app(OrderService::class)->transition($order,'return_requested','customer',$actorId,'Return requested');
   return $rr->load('order','customer','items');
  });
 }
}
