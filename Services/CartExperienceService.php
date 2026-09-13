<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PriyasaCore\Models\Cart;
use Modules\PriyasaCore\Models\CartItem;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\ProductVariant;
use RuntimeException;

final class CartExperienceService
{
    public function resolve(?Customer $customer, ?string $token = null): Cart
    {
        if ($customer) {
            $cart = Cart::query()->where('customer_id', $customer->id)->first();
            if (!$cart && $token) {
                $cart = Cart::query()->where('cart_token', $token)->whereNull('customer_id')->first();
                if ($cart) { $cart->customer_id = $customer->id; $cart->cart_token = null; $cart->save(); }
            }
            return $cart ?: Cart::create(['customer_id'=>$customer->id,'currency'=>config('priyasacore.currency','INR')]);
        }
        if (!Schema::connection('priyasa')->hasColumn('priyasa_carts','cart_token')) throw new RuntimeException('Guest cart support is not installed.');
        $token = trim((string)$token);
        if ($token === '') $token = bin2hex(random_bytes(24));
        return Cart::firstOrCreate(['cart_token'=>$token], ['currency'=>config('priyasacore.currency','INR')]);
    }

    public function snapshot(Cart $cart): array
    {
        $cart->load(['items.variant.product']);
        $subtotal=0.0; $discount=0.0; $items=[]; $warnings=[];
        foreach ($cart->items as $item) {
            $v=$item->variant; $p=$v?->product; $current=$v ? (float)($v->price ?? $p?->price ?? $item->unit_price) : (float)$item->unit_price;
            $mrp=(float)($v?->mrp ?? $p?->mrp ?? $current); $qty=(int)$item->quantity; $line=$current*$qty; $subtotal += $line;
            $snap=$item->price_snapshot !== null ? (float)$item->price_snapshot : (float)$item->unit_price;
            if (abs($current-$snap)>0.009) $warnings[]=['type'=>'price_changed','item_id'=>$item->id,'old_price'=>$snap,'current_price'=>$current];
            $available=$v ? $this->available($v) : 0;
            if ($available < $qty) $warnings[]=['type'=>'stock_changed','item_id'=>$item->id,'requested'=>$qty,'available'=>$available];
            $items[]=['id'=>$item->id,'variant_id'=>$v?->id,'sku'=>$v?->sku,'name'=>$p?->name,'quantity'=>$qty,'unit_price'=>round($current,2),'mrp'=>round($mrp,2),'line_total'=>round($line,2),'available_quantity'=>$available,'in_stock'=>$available>0];
        }
        return ['cart_id'=>$cart->id,'cart_token'=>$cart->cart_token,'currency'=>$cart->currency,'items'=>$items,'subtotal'=>round($subtotal,2),'discount'=>round($discount,2),'total'=>round($subtotal-$discount,2),'item_count'=>count($items),'quantity'=>array_sum(array_column($items,'quantity')),'warnings'=>$warnings,'checkout_ready'=>count($items)>0 && !$this->hasBlockingWarnings($warnings)];
    }

    public function add(Cart $cart, int $variantId, int $quantity): array
    {
        return DB::connection('priyasa')->transaction(function() use ($cart,$variantId,$quantity) {
            $variant=ProductVariant::query()->with('product')->lockForUpdate()->findOrFail($variantId);
            $available=$this->available($variant); $item=$cart->items()->where('variant_id',$variantId)->lockForUpdate()->first(); $new=($item?->quantity ?? 0)+$quantity;
            if ($available < $new) throw new RuntimeException('Requested quantity exceeds available stock.');
            $price=(float)($variant->price ?? $variant->product?->price ?? 0);
            $item ??= new CartItem(['variant_id'=>$variantId]); $item->cart_id=$cart->id; $item->quantity=$new; $item->unit_price=$price; if (Schema::connection('priyasa')->hasColumn('priyasa_cart_items','price_snapshot')) $item->price_snapshot=$price; if (Schema::connection('priyasa')->hasColumn('priyasa_cart_items','added_at')) $item->added_at=now(); $item->save(); $cart->update(['last_added_at'=>now()]);
            return $this->snapshot($cart->fresh());
        });
    }

    public function update(CartItem $item, int $quantity): array
    {
        $variant=$item->variant()->with('product')->first(); if (!$variant) throw new RuntimeException('Product variant is unavailable.');
        if ($this->available($variant)<$quantity) throw new RuntimeException('Requested quantity exceeds available stock.');
        $item->update(['quantity'=>$quantity]); return $this->snapshot($item->cart->fresh());
    }

    public function merge(Customer $customer, ?string $token): array
    {
        if (!$token) return $this->snapshot($this->resolve($customer));
        return DB::connection('priyasa')->transaction(function() use($customer,$token){
            $guest=Cart::where('cart_token',$token)->whereNull('customer_id')->lockForUpdate()->first(); $user=$this->resolve($customer);
            if (!$guest || $guest->id===$user->id) return $this->snapshot($user);
            foreach ($guest->items()->lockForUpdate()->get() as $gi) {
                $ui=$user->items()->where('variant_id',$gi->variant_id)->lockForUpdate()->first(); $qty=($ui?->quantity ?? 0)+(int)$gi->quantity; $v=$gi->variant; $max=$v?$this->available($v):0;
                if ($max>0) $qty=min($qty,$max); if ($qty<1) continue;
                if ($ui) $ui->update(['quantity'=>$qty,'unit_price'=>$gi->unit_price]); else $user->items()->create(['variant_id'=>$gi->variant_id,'quantity'=>$qty,'unit_price'=>$gi->unit_price,'price_snapshot'=>$gi->price_snapshot]);
            }
            $guest->items()->delete(); $guest->delete(); return $this->snapshot($user->fresh());
        });
    }

    private function available(ProductVariant $v): int
    {
        $inventory=$v->inventory; if (!$inventory) return 0; $stock=(int)($inventory->stock_quantity ?? $inventory->quantity ?? 0); $reserved=(int)($inventory->reserved_quantity ?? $inventory->reserved ?? 0); return max(0,$stock-$reserved);
    }
    private function hasBlockingWarnings(array $warnings): bool { foreach($warnings as $w) if(in_array($w['type'],['price_changed','stock_changed'],true)) return true; return false; }
}
