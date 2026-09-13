<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Cart;
use Modules\PriyasaCore\Models\CartItem;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\ProductVariant;
use Modules\PriyasaCore\Services\InventoryService;
use Modules\PriyasaCore\Services\CustomerResolver;

class CartController extends Controller
{
    private function customer(Request $request): Customer
    {
        return app(CustomerResolver::class)->resolve($request->user());
    }

    private function cart(Customer $customer): Cart
    {
        return Cart::firstOrCreate(['customer_id' => $customer->id], ['currency' => config('priyasacore.currency', 'INR')]);
    }

    public function show(Request $request)
    {
        $cart = $this->cart($this->customer($request))->load('items.variant.product');
        return response()->json(['success' => true, 'data' => $cart]);
    }

    public function add(Request $request, InventoryService $inventory)
    {
        $data = $request->validate(['variant_id'=>'required|integer|exists:priyasa_product_variants,id','quantity'=>'required|integer|min:1|max:20']);
        $customer = $this->customer($request);
        $variant = ProductVariant::with('product')->findOrFail($data['variant_id']);

        if ($inventory->available($variant) < $data['quantity']) {
            return response()->json(['success'=>false,'message'=>'Insufficient stock.'], 422);
        }

        $cart = $this->cart($customer);
        $item = $cart->items()->firstOrNew(['variant_id'=>$variant->id]);
        $newQty = ($item->quantity ?? 0) + $data['quantity'];
        if ($inventory->available($variant) < $newQty) {
            return response()->json(['success'=>false,'message'=>'Requested quantity exceeds available stock.'], 422);
        }

        $item->quantity = $newQty;
        $item->unit_price = $variant->price ?? $variant->product->price;
        $item->save();
        $cart->update(['last_added_at'=>now()]);

        return response()->json(['success'=>true,'data'=>$cart->fresh()->load('items.variant.product')]);
    }

    public function update(Request $request, CartItem $item, InventoryService $inventory)
    {
        abort_unless($item->cart->customer_id === $this->customer($request)->id, 403);
        $data = $request->validate(['quantity'=>'required|integer|min:1|max:20']);
        if ($inventory->available($item->variant) < $data['quantity']) return response()->json(['success'=>false,'message'=>'Insufficient stock.'],422);
        $item->update(['quantity'=>$data['quantity']]);
        return response()->json(['success'=>true,'data'=>$item->fresh()]);
    }

    public function remove(Request $request, CartItem $item)
    {
        abort_unless($item->cart->customer_id === $this->customer($request)->id, 403);
        $item->delete();
        return response()->json(['success'=>true]);
    }
}
