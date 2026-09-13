<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Inventory;
use Modules\PriyasaCore\Models\InventoryReservation;
use Modules\PriyasaCore\Models\ProductVariant;
use Modules\PriyasaCore\Models\Order;
use RuntimeException;
final class InventoryReservationService {
 public function reserve(ProductVariant $variant,int $qty,string $reference,?int $orderId=null,?int $ttlSeconds=null): InventoryReservation {
  if($qty<1) throw new RuntimeException('Quantity must be positive.');
  return DB::connection('priyasa')->transaction(function()use($variant,$qty,$reference,$orderId,$ttlSeconds){
   $existing=InventoryReservation::query()->where('variant_id',$variant->id)->where('reference',$reference)->lockForUpdate()->first();
   if($existing && $existing->status==='active') return $existing;
   $inv=Inventory::query()->lockForUpdate()->firstOrCreate(['variant_id'=>$variant->id],['quantity'=>0,'reserved_quantity'=>0,'low_stock_threshold'=>3]);
   if((int)$inv->quantity-(int)$inv->reserved_quantity<$qty) throw new RuntimeException("Insufficient stock for SKU {$variant->sku}.");
   $inv->increment('reserved_quantity',$qty);
   if ($existing && in_array($existing->status, ['released','expired'], true)) {
    $existing->order_id = $orderId ?? $existing->order_id;
    $existing->quantity = $qty;
    $existing->status = 'active';
    $existing->expires_at = now()->addSeconds($ttlSeconds ?? (int)config('p30_checkout.reservation_ttl_seconds', (int) config('priyasacore.inventory_reservation_ttl', 900)));
    $existing->released_at = null;
    $existing->committed_at = null;
    $existing->save();
    return $existing;
   }
   return InventoryReservation::create(['variant_id'=>$variant->id,'order_id'=>$orderId,'reference'=>$reference,'quantity'=>$qty,'status'=>'active','expires_at'=>now()->addSeconds($ttlSeconds ?? (int)config('p30_checkout.reservation_ttl_seconds', (int) config('priyasacore.inventory_reservation_ttl', 900)))]);
  });
 }
 public function release(InventoryReservation $r,string $reason='release'): void { DB::connection('priyasa')->transaction(function()use($r,$reason){$r=InventoryReservation::query()->lockForUpdate()->find($r->id); if(!$r||$r->status!=='active')return; $inv=Inventory::query()->lockForUpdate()->where('variant_id',$r->variant_id)->first(); if($inv){$n=max(0,(int)$inv->reserved_quantity-(int)$r->quantity);$inv->reserved_quantity=$n;$inv->save();} $r->status='released';$r->released_at=now();$r->save();}); }
 public function commit(InventoryReservation $r): void { DB::connection('priyasa')->transaction(function()use($r){$r=InventoryReservation::query()->lockForUpdate()->find($r->id); if(!$r||$r->status!=='active')return; $inv=Inventory::query()->lockForUpdate()->where('variant_id',$r->variant_id)->firstOrFail(); if((int)$inv->reserved_quantity<$r->quantity||(int)$inv->quantity<$r->quantity)throw new RuntimeException('Inventory reservation is inconsistent.'); $inv->quantity-=$r->quantity;$inv->reserved_quantity-=$r->quantity;$inv->save();$r->status='committed';$r->committed_at=now();$r->save();}); }

 public function releaseOrder(Order $order, string $reason = 'order_release'): bool {
  $all = InventoryReservation::query()->where('order_id',$order->id)->orderBy('id')->get();
  foreach ($all->where('status','active') as $reservation) $this->release($reservation,$reason);
  return !$all->isEmpty();
 }

 public function commitOrder(Order $order): bool {
  $reservations = InventoryReservation::query()->where('order_id',$order->id)->where('status','active')->orderBy('id')->get();
  if ($reservations->isEmpty()) return false;
  foreach ($reservations as $reservation) $this->commit($reservation);
  return true;
 }

 public function ensureOrderReservations(Order $order): void {
  foreach ($order->items()->orderBy('id')->get() as $item) {
   $reference = (string)$order->order_number . ':' . (string)$item->variant_id;
   $existing = InventoryReservation::query()->where('variant_id',$item->variant_id)->where('reference',$reference)->where('status','active')->exists();
   if ($existing) continue;
   $variant = ProductVariant::query()->findOrFail($item->variant_id);
   $this->reserve($variant,(int)$item->quantity,$reference,(int)$order->id);
  }
 }

 public function expire(int $limit=500): int { $count=0; InventoryReservation::query()->where('status','active')->whereNotNull('expires_at')->where('expires_at','<=',now())->orderBy('id')->limit($limit)->get()->each(function($r)use(&$count){$this->release($r,'expired');$count++;}); return $count; }
}
