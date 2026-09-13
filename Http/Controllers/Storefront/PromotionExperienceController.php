<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\PromotionExperienceService;

final class PromotionExperienceController extends Controller
{
    public function index(Request $request): array
    { $c=$request->user()?->customer; return app(PromotionExperienceService::class)->discover($c,(float)$request->query('subtotal',0),$request->boolean('prepaid')); }
    public function validateCode(Request $request): array
    { $d=$request->validate(['code'=>['required','string','max:64'],'subtotal'=>['required','numeric','min:0'],'prepaid'=>['sometimes','boolean']]); $c=$request->user()?->customer; return app(PromotionExperienceService::class)->validateCode($c,[],(float)$d['subtotal'],(string)$d['code'],(bool)($d['prepaid']??false)); }
    public function best(Request $request): array
    { $d=$request->validate(['subtotal'=>['required','numeric','min:0'],'prepaid'=>['sometimes','boolean'],'items'=>['sometimes','array']]); $c=$request->user()?->customer; return app(PromotionExperienceService::class)->best($c,$d['items']??[],(float)$d['subtotal'],(bool)($d['prepaid']??false)); }
    public function loyalty(Request $request): array
    { $d=$request->validate(['subtotal'=>['required','numeric','min:0'],'points'=>['required','integer','min:1']]); return app(PromotionExperienceService::class)->applyLoyalty($request->user()?->customer,(float)$d['subtotal'],(int)$d['points']); }
}
