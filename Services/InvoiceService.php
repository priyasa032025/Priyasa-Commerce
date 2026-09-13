<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Invoice;
use Modules\PriyasaCore\Models\Order;
use RuntimeException;
final class InvoiceService {
 public function issue(Order $order): Invoice { return DB::connection('priyasa')->transaction(function() use($order){
  $order=Order::query()->with(['items','customer','shippingAddress','invoice'])->lockForUpdate()->findOrFail($order->id);
  if(!in_array($order->status,['confirmed','processing','packed','shipped','in_transit','out_for_delivery','delivered'],true)) throw new RuntimeException('Invoice can only be issued after order confirmation.');
  if($order->invoice) return $order->invoice;
  $lines=[]; foreach($order->items as $item){ $tax=(float)$item->tax_amount; $total=(float)$item->line_total; $taxable=max(0,$total-$tax); $rate=$taxable>0?round(($tax/$taxable)*100,2):0; $lines[]=['order_item_id'=>$item->id,'sku'=>$item->sku,'product_name'=>$item->product_name,'variant_label'=>$item->variant_label,'quantity'=>(int)$item->quantity,'unit_price'=>(float)$item->unit_price,'taxable_amount'=>$taxable,'tax_amount'=>$tax,'gst_rate'=>$rate,'line_total'=>$total]; }
  $number=config('priyasacore.invoice_number_prefix','PRI-INV').'-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,10));
  return Invoice::create(['order_id'=>$order->id,'invoice_number'=>$number,'status'=>'issued','currency'=>$order->currency,'subtotal'=>$order->subtotal,'discount_total'=>$order->discount_total,'taxable_total'=>max(0,(float)$order->subtotal-(float)$order->discount_total),'tax_total'=>$order->tax_total,'shipping_total'=>$order->shipping_total,'grand_total'=>$order->grand_total,'seller_gstin'=>config('priyasacore.gstin'),'seller_state'=>config('priyasacore.seller_state'),'billing_address'=>data_get($order->metadata,'billing_address') ?? null,'shipping_address'=>$order->shippingAddress?->toArray(),'lines'=>$lines,'issued_at'=>now()]);
 }); }
}
