<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Wishlist;
use Modules\PriyasaCore\Models\ProductVariant;
final class WishlistService {
 public function toggle(Customer $customer, int $variantId): array { $variant=ProductVariant::findOrFail($variantId); $item=Wishlist::where('customer_id',$customer->id)->where('variant_id',$variant->id)->first(); if($item){$item->delete();return ['wishlisted'=>false];} Wishlist::create(['customer_id'=>$customer->id,'variant_id'=>$variant->id]); return ['wishlisted'=>true]; }
 public function list(Customer $customer){return Wishlist::with('variant.product')->where('customer_id',$customer->id)->latest()->get();}
}
