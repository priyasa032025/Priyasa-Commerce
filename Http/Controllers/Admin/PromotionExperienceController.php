<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Models\Coupon;
use Modules\PriyasaCore\Models\PromotionCampaign;

final class PromotionExperienceController extends Controller
{
    public function performance(Request $request): array
    {
        $days=min(max((int)$request->query('days',30),1),365); $since=now()->subDays($days);
        $q=class_exists('Modules\\PriyasaCore\\Models\\PromotionEvent') ? \Modules\PriyasaCore\Models\PromotionEvent::query()->where('created_at','>=',$since) : null;
        if (!$q) return ['period_days'=>$days,'events'=>[],'summary'=>[]];
        $rows=$q->get()->groupBy('event_type')->map(fn($x)=>['count'=>$x->count(),'discount'=>(float)$x->sum('discount_amount')])->toArray();
        return ['period_days'=>$days,'events'=>$rows,'active_coupons'=>Coupon::where('is_active',true)->count(),'active_campaigns'=>class_exists(PromotionCampaign::class)?PromotionCampaign::where('is_active',true)->count():0];
    }
}
