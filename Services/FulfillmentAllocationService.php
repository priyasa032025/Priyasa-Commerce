<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\PriyasaCore\Models\Order;
use RuntimeException;

final class FulfillmentAllocationService
{
    public function allocateOrder(Order $order, ?string $pincode=null): array
    {
        return DB::connection('priyasa')->transaction(function() use ($order,$pincode) {
            $items=$order->items()->orderBy('id')->lockForUpdate()->get();
            if($items->isEmpty()) throw ValidationException::withMessages(['order'=>'Order has no items.']);
            $existing=DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('order_id',$order->id)->whereIn('status',['allocated','picked','packed'])->exists();
            if($existing) return $this->summary($order);
            $warehouses=DB::connection('priyasa')->table('priyasa_warehouses as w')->where('w.status','active')->where('w.supports_fulfillment',true)
                ->when($pincode,function($q)use($pincode){$q->leftJoin('priyasa_warehouse_serviceability as ws',function($j)use($pincode){$j->on('ws.warehouse_id','=','w.id')->where('ws.pincode','=',$pincode);}); $q->where(function($x){$x->whereNull('ws.id')->orWhere('ws.serviceable',true);});})
                ->select('w.*')->orderBy('w.priority')->orderBy('w.id')->lockForUpdate()->get();
            if($warehouses->isEmpty()) throw ValidationException::withMessages(['warehouse'=>'No fulfillment warehouse is available.']);
            foreach($items as $item){
                $remaining=(int)$item->quantity;
                foreach($warehouses as $w){
                    if($remaining<=0) break;
                    $stock=DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('warehouse_id',$w->id)->where('variant_id',$item->variant_id)->lockForUpdate()->first();
                    if(!$stock) continue;
                    $available=max(0,(int)$stock->quantity-(int)$stock->reserved_quantity);
                    if($available<1) continue;
                    $take=min($remaining,$available);
                    DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('id',$stock->id)->update(['reserved_quantity'=>(int)$stock->reserved_quantity+$take,'updated_at'=>now()]);
                    $ref=$order->order_number.':'.$item->id.':'.$w->id;
                    DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->insert(['order_id'=>$order->id,'order_item_id'=>$item->id,'warehouse_id'=>$w->id,'variant_id'=>$item->variant_id,'quantity'=>$take,'status'=>'allocated','reference'=>$ref,'created_at'=>now(),'updated_at'=>now()]);
                    DB::connection('priyasa')->table('priyasa_inventory_ledger')->insert(['warehouse_id'=>$w->id,'variant_id'=>$item->variant_id,'quantity_delta'=>0,'reserved_delta'=>$take,'quantity_after'=>(int)$stock->quantity,'reason'=>'fulfillment_allocation','reference_type'=>'order','reference_id'=>(string)$order->id,'created_at'=>now(),'updated_at'=>now()]);
                    $remaining-=$take;
                }
                if($remaining>0) throw ValidationException::withMessages(['inventory'=>"Insufficient fulfillable stock for order item {$item->id}."]);
            }
            return $this->summary($order);
        });
    }

    public function markPicked(int $allocationId): array { return $this->transition($allocationId,'allocated','picked','picked_at'); }
    public function markPacked(int $allocationId): array { return $this->transition($allocationId,'picked','packed','packed_at'); }

    public function cancelAllocation(int $allocationId): array {
        return DB::connection('priyasa')->transaction(function()use($allocationId){
            $a=DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('id',$allocationId)->lockForUpdate()->first();
            if(!$a || !in_array($a->status,['allocated','picked'],true)) throw ValidationException::withMessages(['allocation'=>'Only allocated or picked allocations can be cancelled.']);
            $s=DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('warehouse_id',$a->warehouse_id)->where('variant_id',$a->variant_id)->lockForUpdate()->firstOrFail();
            $new=max(0,(int)$s->reserved_quantity-(int)$a->quantity); DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('id',$s->id)->update(['reserved_quantity'=>$new,'updated_at'=>now()]);
            DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('id',$a->id)->update(['status'=>'cancelled','cancelled_at'=>now(),'updated_at'=>now()]);
            return (array)DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('id',$a->id)->first();
        });
    }

    public function summary(Order $order): array { $rows=DB::connection('priyasa')->table('priyasa_fulfillment_allocations as a')->join('priyasa_warehouses as w','w.id','=','a.warehouse_id')->where('a.order_id',$order->id)->select('a.*','w.code as warehouse_code','w.name as warehouse_name')->orderBy('a.id')->get(); return ['order_id'=>$order->id,'complete'=>$this->isComplete($order->id),'allocations'=>$rows]; }
    private function isComplete(int $orderId): bool { $expected=(int)DB::connection('priyasa')->table('priyasa_order_items')->where('order_id',$orderId)->sum('quantity'); $allocated=(int)DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('order_id',$orderId)->whereIn('status',['allocated','picked','packed'])->sum('quantity'); return $expected>0 && $expected===$allocated; }
    private function transition(int $id,string $from,string $to,string $stamp): array { return DB::connection('priyasa')->transaction(function()use($id,$from,$to,$stamp){$a=DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('id',$id)->lockForUpdate()->first(); if(!$a||$a->status!==$from)throw ValidationException::withMessages(['allocation'=>"Allocation must be {$from} before {$to}."]); DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('id',$id)->update(['status'=>$to,$stamp=>now(),'updated_at'=>now()]); return (array)DB::connection('priyasa')->table('priyasa_fulfillment_allocations')->where('id',$id)->first();}); }

    public function createTransfer(int $from,int $to,int $variant,int $quantity,?string $note=null): array {
        if($from===$to||$quantity<1) throw ValidationException::withMessages(['transfer'=>'Invalid warehouse or quantity.']);
        return DB::connection('priyasa')->transaction(function()use($from,$to,$variant,$quantity,$note){
            $s=DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('warehouse_id',$from)->where('variant_id',$variant)->lockForUpdate()->first();
            if(!$s || ((int)$s->quantity-(int)$s->reserved_quantity)<$quantity) throw ValidationException::withMessages(['inventory'=>'Insufficient transferable stock.']);
            $ref='TR-'.strtoupper(bin2hex(random_bytes(6)));
            DB::connection('priyasa')->table('priyasa_warehouse_transfers')->insert(['reference'=>$ref,'from_warehouse_id'=>$from,'to_warehouse_id'=>$to,'variant_id'=>$variant,'quantity'=>$quantity,'status'=>'requested','note'=>$note,'created_at'=>now(),'updated_at'=>now()]);
            return (array)DB::connection('priyasa')->table('priyasa_warehouse_transfers')->where('reference',$ref)->first();
        });
    }
    public function receiveTransfer(int $id,int $received): array {
        return DB::connection('priyasa')->transaction(function()use($id,$received){$t=DB::connection('priyasa')->table('priyasa_warehouse_transfers')->where('id',$id)->lockForUpdate()->first(); if(!$t||!in_array($t->status,['requested','in_transit'],true))throw ValidationException::withMessages(['transfer'=>'Transfer is not receivable.']); $remaining=(int)$t->quantity-(int)$t->received_quantity; if($received<1||$received>$remaining)throw ValidationException::withMessages(['received_quantity'=>'Invalid received quantity.']); $src=DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('warehouse_id',$t->from_warehouse_id)->where('variant_id',$t->variant_id)->lockForUpdate()->firstOrFail(); if(((int)$src->quantity-(int)$src->reserved_quantity)<$received)throw new RuntimeException('Source stock is no longer available.'); $dst=DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('warehouse_id',$t->to_warehouse_id)->where('variant_id',$t->variant_id)->lockForUpdate()->first(); if(!$dst){DB::connection('priyasa')->table('priyasa_warehouse_inventory')->insert(['warehouse_id'=>$t->to_warehouse_id,'variant_id'=>$t->variant_id,'quantity'=>$received,'reserved_quantity'=>0,'damaged_quantity'=>0,'in_transit_quantity'=>0,'reorder_level'=>0,'created_at'=>now(),'updated_at'=>now()]);}else DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('id',$dst->id)->update(['quantity'=>(int)$dst->quantity+$received,'updated_at'=>now()]); DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('id',$src->id)->update(['quantity'=>(int)$src->quantity-$received,'updated_at'=>now()]); $new=(int)$t->received_quantity+$received; DB::connection('priyasa')->table('priyasa_warehouse_transfers')->where('id',$id)->update(['received_quantity'=>$new,'status'=>$new===(int)$t->quantity?'received':'in_transit','updated_at'=>now()]); return (array)DB::connection('priyasa')->table('priyasa_warehouse_transfers')->where('id',$id)->first();});
    }
}
