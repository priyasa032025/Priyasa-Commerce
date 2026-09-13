<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Http\Controllers\Controller;
use Modules\PriyasaCore\Models\Coupon;
use Modules\PriyasaCore\Models\CouponRedemption;
use Modules\PriyasaCore\Models\PromotionCampaign;
use Throwable;

final class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $query = Coupon::query()->orderByDesc('created_at');
        if ($q !== '') $query->where('code', 'like', "%{$q}%");
        if ($status === 'active') $query->where('is_active', true);
        if ($status === 'inactive') $query->where('is_active', false);
        $page = $query->paginate(min(max((int) $request->query('per_page', 25), 1), 100));
        return response()->json(['success'=>true,'data'=>$page]);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $coupon->loadCount('redemptions');
        return response()->json(['success'=>true,'data'=>$coupon]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper(trim($data['code']));
        $data['usage_count'] = 0;
        $coupon = Coupon::create($data);
        return response()->json(['success'=>true,'data'=>$coupon], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $this->validated($request, $coupon->id);
        if (isset($data['code'])) $data['code'] = strtoupper(trim($data['code']));
        $coupon->update($data);
        return response()->json(['success'=>true,'data'=>$coupon->fresh()]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        if (CouponRedemption::where('coupon_id', $coupon->id)->exists()) {
            $coupon->update(['is_active'=>false]);
            return response()->json(['success'=>true,'message'=>'Coupon has redemptions and was deactivated instead.','data'=>$coupon->fresh()]);
        }
        $coupon->delete();
        return response()->json(['success'=>true,'message'=>'Coupon deleted.']);
    }

    public function toggle(Request $request, Coupon $coupon): JsonResponse
    {
        $coupon->update(['is_active'=>(bool)$request->boolean('is_active')]);
        return response()->json(['success'=>true,'data'=>$coupon->fresh()]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $query = PromotionCampaign::query()->orderBy('priority')->orderByDesc('created_at');
        if ($request->filled('search')) $query->where('name', 'like', '%'.trim((string)$request->query('search')).'%');
        return response()->json(['success'=>true,'data'=>$query->paginate(min(max((int)$request->query('per_page',25),1),100))]);
    }

    public function campaignStore(Request $request): JsonResponse
    {
        $data=$this->campaignValidated($request); $campaign=PromotionCampaign::create($data);
        return response()->json(['success'=>true,'data'=>$campaign],201);
    }

    public function campaignUpdate(Request $request, PromotionCampaign $campaign): JsonResponse
    {
        $campaign->update($this->campaignValidated($request,false));
        return response()->json(['success'=>true,'data'=>$campaign->fresh()]);
    }

    public function campaignToggle(Request $request, PromotionCampaign $campaign): JsonResponse
    {
        $campaign->update(['is_active'=>$request->boolean('is_active')]);
        return response()->json(['success'=>true,'data'=>$campaign->fresh()]);
    }

    private function campaignValidated(Request $request, bool $required=true): array
    {
        $requiredOrNullable=$required?'required':'sometimes';
        return $request->validate([
            'name'=>[$requiredOrNullable,'string','max:160'],'type'=>['sometimes','in:automatic,flash_sale,segment'],
            'discount_type'=>[$requiredOrNullable,'in:percentage,fixed'],'discount_value'=>[$requiredOrNullable,'numeric','min:0.01'],
            'minimum_cart_value'=>['nullable','numeric','min:0'],'maximum_discount'=>['nullable','numeric','min:0'],
            'usage_limit'=>['nullable','integer','min:1'],'per_customer_limit'=>['nullable','integer','min:1'],
            'priority'=>['sometimes','integer','min:0'],'stackable'=>['sometimes','boolean'],'first_order_only'=>['sometimes','boolean'],
            'is_active'=>['sometimes','boolean'],'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date','after_or_equal:starts_at'],'rules'=>['nullable','array'],
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:priyasa_coupons,code'.($ignoreId ? ','.$ignoreId : '');
        return $request->validate([
            'code'=>['required','string','max:64',$unique],
            'type'=>['required','in:percentage,fixed'],
            'value'=>['required','numeric','min:0.01'],
            'minimum_cart_value'=>['nullable','numeric','min:0'],
            'maximum_discount'=>['nullable','numeric','min:0'],
            'usage_limit'=>['nullable','integer','min:1'],
            'per_customer_limit'=>['nullable','integer','min:1'],
            'starts_at'=>['nullable','date'],
            'ends_at'=>['nullable','date','after_or_equal:starts_at'],
            'is_active'=>['sometimes','boolean'],
            'rules'=>['nullable','array'],
        ]);
    }
}
