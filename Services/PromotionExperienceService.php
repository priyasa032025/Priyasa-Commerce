<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Coupon;
use Modules\PriyasaCore\Models\CouponRedemption;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\PromotionCampaign;
use Modules\PriyasaCore\Models\PromotionEvent;
use Modules\PriyasaCore\Models\LoyaltyAccount;
use RuntimeException;

final class PromotionExperienceService
{
    public function discover(?Customer $customer, float $subtotal = 0, bool $prepaid = false): array
    {
        $out = [];
        $coupons = Coupon::query()->where('is_active', true)->orderBy('priority')->orderByDesc('created_at')->limit(100)->get();
        foreach ($coupons as $coupon) {
            if (!$this->eligible($coupon, $customer, [], $subtotal, $prepaid, false)) continue;
            $out[] = $this->present($coupon, 'coupon');
        }
        $campaigns = SchemaSafe::table('priyasa_promotion_campaigns') ? PromotionCampaign::query()->where('is_active', true)->orderBy('priority')->limit(100)->get() : collect();
        foreach ($campaigns as $campaign) {
            if (!$this->eligible($campaign, $customer, [], $subtotal, $prepaid, false)) continue;
            $out[] = $this->present($campaign, 'campaign');
        }
        usort($out, fn(array $a,array $b) => [$a['priority'], -$a['estimated_discount']] <=> [$b['priority'], -$b['estimated_discount']]);
        return ['items'=>array_values($out), 'count'=>count($out)];
    }

    public function best(?Customer $customer, array $items, float $subtotal, bool $prepaid = false): array
    {
        $candidates = [];
        foreach (Coupon::query()->where('is_active', true)->get() as $coupon) {
            if (!$this->eligible($coupon,$customer,$items,$subtotal,$prepaid,false)) continue;
            $candidates[] = ['kind'=>'coupon','promotion'=>$coupon,'discount'=>$this->discount($coupon,$subtotal)];
        }
        if (SchemaSafe::table('priyasa_promotion_campaigns')) foreach (PromotionCampaign::query()->where('is_active',true)->get() as $campaign) {
            if (!$this->eligible($campaign,$customer,$items,$subtotal,$prepaid,false)) continue;
            $candidates[] = ['kind'=>'campaign','promotion'=>$campaign,'discount'=>$this->discount($campaign,$subtotal)];
        }
        usort($candidates, fn($a,$b) => [$b['discount'], (int)$a['promotion']->priority] <=> [$a['discount'], (int)$b['promotion']->priority]);
        if (!$candidates) return ['best'=>null,'savings'=>0,'alternatives'=>[]];
        return ['best'=>$this->present($candidates[0]['promotion'],$candidates[0]['kind'],(float)$candidates[0]['discount']), 'savings'=>round((float)$candidates[0]['discount'],2), 'alternatives'=>array_map(fn($x)=>$this->present($x['promotion'],$x['kind'],(float)$x['discount']),array_slice($candidates,1,5))];
    }

    public function validateCode(?Customer $customer, array $items, float $subtotal, string $code, bool $prepaid=false): array
    {
        $code = strtoupper(trim($code));
        $coupon = Coupon::query()->whereRaw('UPPER(code)=?',[$code])->first();
        if (!$coupon) throw new RuntimeException('Coupon is invalid or inactive.');
        $this->eligible($coupon,$customer,$items,$subtotal,$prepaid,true);
        $discount = $this->discount($coupon,$subtotal);
        $this->event('validated',$customer,$coupon,null,null,$discount,['subtotal'=>$subtotal]);
        return ['valid'=>true,'code'=>$coupon->code,'discount'=>$discount,'coupon'=>$this->present($coupon,'coupon',$discount)];
    }

    public function applyLoyalty(?Customer $customer, float $subtotal, int $points): array
    {
        if (!$customer) throw new RuntimeException('Login required to use loyalty points.');
        if ($points <= 0) throw new RuntimeException('Points must be positive.');
        $account = class_exists(LoyaltyAccount::class) ? LoyaltyAccount::query()->where('customer_id',$customer->id)->first() : null;
        if (!$account || (int)$account->points < $points) throw new RuntimeException('Insufficient loyalty points.');
        $rate = (float)config('priyasacore.loyalty.point_value', 1.0);
        $discount = min($subtotal, round($points*$rate,2));
        return ['points'=>$points,'discount'=>$discount,'remaining_points'=>(int)$account->points-$points,'conversion_rate'=>$rate];
    }

    private function eligible($promo, ?Customer $customer, array $items, float $subtotal, bool $prepaid, bool $throw): bool
    {
        $fail = function(string $message) use ($throw): bool { if ($throw) throw new RuntimeException($message); return false; };
        if (!$promo->is_active) return $fail('Promotion is inactive.');
        if ($promo->starts_at && now()->lt($promo->starts_at)) return $fail('Promotion is not active yet.');
        if ($promo->ends_at && now()->gt($promo->ends_at)) return $fail('Promotion has expired.');
        if ($subtotal < (float)$promo->minimum_cart_value) return $fail('Minimum cart value not reached.');
        if (($promo->usage_limit ?? null) !== null && (int)$promo->usage_count >= (int)$promo->usage_limit) return $fail('Promotion usage limit reached.');
        if (!empty($promo->prepaid_only) && !$prepaid) return $fail('This promotion is available on prepaid orders only.');
        if ($promo->first_order_only && (!$customer || $customer->orders()->exists())) return $fail('Promotion is valid only for first orders.');
        if ($customer && $promo instanceof Coupon && $promo->per_customer_limit !== null && CouponRedemption::where('coupon_id',$promo->id)->where('customer_id',$customer->id)->count() >= (int)$promo->per_customer_limit) return $fail('Customer usage limit reached.');
        $segments = is_array($promo->customer_segments ?? null) ? $promo->customer_segments : [];
        if ($segments && $customer) {
            $segment = (string)($customer->segment ?? '');
            if ($segment !== '' && !in_array($segment,$segments,true)) return $fail('Promotion is not available for this customer segment.');
        }
        $rules = is_array($promo->rules ?? null) ? $promo->rules : [];
        $qty = array_sum(array_map(fn($i)=>(int)($i['quantity'] ?? 0),$items));
        if (isset($rules['min_quantity']) && $qty < (int)$rules['min_quantity']) return $fail('Minimum quantity condition not reached.');
        $variants = array_values(array_filter(array_map(fn($i)=>$i['variant'] ?? null,$items)));
        if (!empty($rules['product_ids']) && $variants && !array_intersect(array_map(fn($v)=>(int)$v->product_id,$variants),array_map('intval',(array)$rules['product_ids']))) return $fail('Promotion does not apply to these products.');
        return true;
    }

    private function discount($promo,float $subtotal): float
    { $type=(string)($promo->discount_type ?? $promo->type); $value=(float)($promo->discount_value ?? $promo->value); $d=$type==='fixed'?$value:$subtotal*($value/100); if((float)($promo->maximum_discount ?? 0)>0)$d=min($d,(float)$promo->maximum_discount); return round(max(0,min($d,$subtotal)),2); }

    private function present($promo,string $kind,?float $discount=null): array
    { return ['kind'=>$kind,'id'=>(int)$promo->id,'code'=>$promo->code ?? null,'name'=>$promo->name ?? ($promo->display_label ?? $promo->code ?? 'Offer'),'description'=>$promo->description ?? null,'discount_type'=>$promo->discount_type ?? $promo->type,'discount_value'=>(float)($promo->discount_value ?? $promo->value),'estimated_discount'=>round($discount ?? $this->discount($promo,1000),2),'minimum_cart_value'=>(float)$promo->minimum_cart_value,'maximum_discount'=>$promo->maximum_discount===null?null:(float)$promo->maximum_discount,'priority'=>(int)($promo->priority ?? 100),'stackable'=>(bool)($promo->stackable ?? false),'prepaid_only'=>(bool)($promo->prepaid_only ?? false),'ends_at'=>$promo->ends_at?->toISOString()]; }

    private function event(string $type,?Customer $customer,$coupon=null,$campaign=null,$order=null,float $discount=0,array $meta=[]): void
    { if (!SchemaSafe::table('priyasa_promotion_events')) return; PromotionEvent::create(['customer_id'=>$customer?->id,'coupon_id'=>$coupon?->id,'campaign_id'=>$campaign?->id,'order_id'=>$order?->id,'event_type'=>$type,'discount_amount'=>$discount,'code'=>$coupon?->code,'metadata'=>$meta]); }
}

final class SchemaSafe
{
    public static function table(string $table): bool { try { return \Illuminate\Support\Facades\Schema::connection('priyasa')->hasTable($table); } catch (\Throwable) { return false; } }
}
