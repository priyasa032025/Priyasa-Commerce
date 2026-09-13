<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\CartItem;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\CartExperienceService;
use RuntimeException;

final class CartExperienceController extends Controller
{
    private function customer(Request $request): ?Customer
    {
        $user=$request->user(); if(!$user) return null;
        return Customer::where('user_id',$user->id)->orWhere('phone',(string)$user->phone)->first();
    }
    public function show(Request $r, CartExperienceService $s){ return response()->json(['success'=>true,'type'=>'cart.experience','data'=>$s->snapshot($s->resolve($this->customer($r),$r->header('X-Cart-Token')))]); }
    public function add(Request $r, CartExperienceService $s){ $d=$r->validate(['variant_id'=>'required|integer','quantity'=>'required|integer|min:1|max:20']); try{$data=$s->add($s->resolve($this->customer($r),$r->header('X-Cart-Token')),(int)$d['variant_id'],(int)$d['quantity']);return response()->json(['success'=>true,'type'=>'cart.updated','data'=>$data]);}catch(RuntimeException $e){return response()->json(['success'=>false,'errors'=>[['code'=>'CART_UPDATE_FAILED','message'=>$e->getMessage()]]],422);} }
    public function update(Request $r, CartItem $item, CartExperienceService $s){ $d=$r->validate(['quantity'=>'required|integer|min:1|max:20']); $cart=$s->resolve($this->customer($r),$r->header('X-Cart-Token')); abort_unless((int)$item->cart_id===(int)$cart->id,403); try{return response()->json(['success'=>true,'type'=>'cart.updated','data'=>$s->update($item,(int)$d['quantity'])]);}catch(RuntimeException $e){return response()->json(['success'=>false,'errors'=>[['code'=>'CART_UPDATE_FAILED','message'=>$e->getMessage()]]],422);} }
    public function remove(Request $r, CartItem $item, CartExperienceService $s){$cart=$s->resolve($this->customer($r),$r->header('X-Cart-Token'));abort_unless((int)$item->cart_id===(int)$cart->id,403);$item->delete();return response()->json(['success'=>true,'type'=>'cart.updated','data'=>$s->snapshot($cart->fresh())]);}
    public function merge(Request $r, CartExperienceService $s){$c=$this->customer($r);abort_unless($c,401);return response()->json(['success'=>true,'type'=>'cart.merged','data'=>$s->merge($c,$r->header('X-Cart-Token'))]);}
}
