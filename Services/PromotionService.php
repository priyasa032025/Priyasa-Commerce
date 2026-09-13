<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Coupon;
use Modules\PriyasaCore\Models\CouponRedemption;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\PromotionCampaign;
use RuntimeException;

final class PromotionService
{
    public function calculate(?Customer $customer, array $items, float $subtotal, ?string $code=null, bool $prepaid=true): array
    {
        $applied=[];
        if ($code !== null && trim($code)!=='') {
            $coupon=Coupon::query()->whereRaw('UPPER(code)=?',[strtoupper(trim($code))])->first();
            if (!$coupon) throw new RuntimeException('Coupon is invalid or inactive.');
            $this->assertEligible($coupon,$customer,$items,$subtotal,$prepaid);
            $d=$this->discount($coupon,$subtotal); $applied[]=['kind'=>'coupon','id'=>$coupon->id,'code'=>$coupon->code,'discount'=>$d,'stackable'=>(bool)$coupon->stackable,'priority'=>(int)$coupon->priority];
        }
        if (class_exists(PromotionCampaign::class)) foreach(PromotionCampaign::query()->where('is_active',true)->orderBy('priority')->orderBy('id')->get() as $campaign){
            if (!$this->eligibleRules($campaign,$customer,$items,$subtotal,$prepaid)) continue;
            if (collect($applied)->contains(fn($x)=>!$x['stackable']) && !$campaign->stackable) continue;
            $base=max(0,$subtotal-array_sum(array_column($applied,'discount'))); $d=$this->discount($campaign,$base); if($d<=0)continue;
            $applied[]=['kind'=>'campaign','id'=>$campaign->id,'name'=>$campaign->name,'discount'=>$d,'stackable'=>(bool)$campaign->stackable,'priority'=>(int)$campaign->priority];
            if(!$campaign->stackable)break;
        }
        $total=min($subtotal,round(array_sum(array_column($applied,'discount')),2));
        return ['discount'=>round($total,2),'coupon'=>$code?($applied[0]??null):null,'promotions'=>$applied];
    }

    public function apply(?string $code,array $quote): array { return ['discount'=>(float)($quote['discount_total']??0),'coupon'=>$quote['coupon']??null,'promotions'=>$quote['promotions']??[]]; }

    public function reserveForOrder(Order $order,Customer $customer): void
    {
        $code=trim((string)($order->metadata['coupon_code']??'')); if($code==='')return;
        DB::connection('priyasa')->transaction(function()use($code,$order,$customer):void{
            $coupon=Coupon::query()->whereRaw('UPPER(code)=?',[strtoupper($code)])->lockForUpdate()->first(); if(!$coupon)throw new RuntimeException('Coupon is no longer available.');
            $items=$order->items()->with('variant.product')->get()->map(fn($i)=>['variant'=>$i->variant,'quantity'=>$i->quantity])->all();
            $prepaid=!in_array(strtolower((string)($order->payment_method??'')),['cod','cash_on_delivery'],true);
            $this->assertEligible($coupon,$customer,$items,(float)$order->subtotal,$prepaid);
            if(CouponRedemption::where('order_id',$order->id)->exists())return;
            CouponRedemption::create(['coupon_id'=>$coupon->id,'customer_id'=>$customer->id,'order_id'=>$order->id,'discount_amount'=>$order->discount_total]); $coupon->increment('usage_count');
        });
    }
    public function redeemForOrder(Order $order,Customer $customer):void{if(!CouponRedemption::where('order_id',$order->id)->exists())$this->reserveForOrder($order,$customer);}
    public function releaseForOrder(Order $order):void{
        $r=CouponRedemption::where('order_id',$order->id)->first();if(!$r)return;DB::connection('priyasa')->transaction(function()use($r){$c=Coupon::lockForUpdate()->find($r->coupon_id);$r->delete();if($c&&$c->usage_count>0)$c->decrement('usage_count');});
    }
    private function assertEligible(Coupon $p,?Customer $c,array $items,float $subtotal,bool $prepaid):void{if(!$this->eligibleRules($p,$c,$items,$subtotal,$prepaid))throw new RuntimeException('Coupon conditions are not satisfied.');if($c&&$p->per_customer_limit!==null&&CouponRedemption::where('coupon_id',$p->id)->where('customer_id',$c->id)->count()>=(int)$p->per_customer_limit)throw new RuntimeException('Coupon customer limit reached.');}
    private function eligibleRules($p,?Customer $c,array $items,float $subtotal,bool $prepaid):bool{
        if(!$p->is_active||($p->starts_at&&now()->lt($p->starts_at))||($p->ends_at&&now()->gt($p->ends_at)))return false;
        if($subtotal<(float)$p->minimum_cart_value)return false;if($p->usage_limit!==null&&(int)$p->usage_count>=(int)$p->usage_limit)return false;if(($p->prepaid_only??false)&&!$prepaid)return false;
        if($p->first_order_only&&(!$c||$c->orders()->exists()))return false;
        $segments=is_array($p->customer_segments??null)?$p->customer_segments:[];if($segments&&$c){$seg=(string)($c->segment??'');if($seg!==''&&!in_array($seg,$segments,true))return false;}
        $r=is_array($p->rules??null)?$p->rules:[];$qty=array_sum(array_map(fn($i)=>(int)($i['quantity']??0),$items));if(isset($r['min_quantity'])&&$qty<(int)$r['min_quantity'])return false;
        $variants=array_values(array_filter(array_map(fn($i)=>$i['variant']??null,$items)));
        if(!empty($r['product_ids'])&&$variants&&!array_intersect(array_map(fn($v)=>(int)$v->product_id,$variants),array_map('intval',(array)$r['product_ids'])))return false;
        return true;
    }
    private function discount($p,float $base):float{$d=((string)($p->discount_type??$p->type))==='fixed'?(float)($p->discount_value??$p->value):$base*((float)($p->discount_value??$p->value)/100);if((float)($p->maximum_discount??0)>0)$d=min($d,(float)$p->maximum_discount);return round(max(0,min($d,$base)),2);}
}
