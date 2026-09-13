<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\{Order,OrderItem,Product,Variant};
use RuntimeException;

final class CustomerOrderExperienceService
{
    public function timeline(Order $order): array
    {
        $events = DB::connection('priyasa')->table('priyasa_order_status_histories')
            ->where('order_id', $order->id)->orderBy('created_at')->get()->map(fn($e) => [
                'type'=>'order_status','status'=>$e->status ?? null,'message'=>$e->message ?? null,'created_at'=>$e->created_at,
            ])->values()->all();
        if (!$events) $events = [['type'=>'order_status','status'=>$order->status,'message'=>'Order status','created_at'=>$order->updated_at]];
        return ['order_id'=>$order->id,'status'=>$order->status,'events'=>$events];
    }

    public function reorder(Order $order, $customer): array
    {
        abort_unless((int)$order->customer_id === (int)$customer->id, 403);
        $items=[];
        foreach ($order->items as $item) {
            $variant = $item->variant ?? Variant::find($item->variant_id);
            if (!$variant || !(bool)($variant->is_active ?? true)) continue;
            $product = $item->product ?? Product::find($item->product_id);
            if (!$product || !(bool)($product->is_active ?? true)) continue;
            $items[]=['product_id'=>$product->id,'variant_id'=>$variant->id,'quantity'=>(int)$item->quantity];
        }
        return ['items'=>$items,'count'=>count($items)];
    }

    public function reasons(): array
    {
        return [
            'cancellation'=>['Changed my mind','Ordered by mistake','Found a better price','Delivery taking too long','Other'],
            'return'=>['Size/fit issue','Wrong item received','Damaged item','Quality issue','Item not as expected','Other'],
            'exchange'=>['Size/fit issue','Wrong variant','Damaged item','Other'],
        ];
    }
}
