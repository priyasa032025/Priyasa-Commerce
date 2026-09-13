<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PriyasaCore\Services\AnalyticsService;

final class AnalyticsController extends Controller
{
    public function overview(Request $request, AnalyticsService $analytics): JsonResponse { [$from,$to]=$this->range($request); return response()->json(['data'=>$analytics->overview($from,$to)]); }
    public function daily(Request $request, AnalyticsService $analytics): JsonResponse { [$from,$to]=$this->range($request); return response()->json(['data'=>$analytics->daily($from,$to)]); }
    public function products(Request $request, AnalyticsService $analytics): JsonResponse { [$from,$to]=$this->range($request); $limit=min(max((int)$request->input('limit',50),1),200); return response()->json(['data'=>$analytics->products($from,$to,$limit)]); }
    public function rebuild(Request $request, AnalyticsService $analytics): JsonResponse { $date=Carbon::parse($request->input('date',now()->subDay()->toDateString()))->startOfDay(); $analytics->persistDaily($date); return response()->json(['data'=>['date'=>$date->toDateString(),'status'=>'rebuilt']]); }
    private function range(Request $r): array { $to=$r->filled('to')?Carbon::parse($r->input('to'))->endOfDay():now()->endOfDay(); $from=$r->filled('from')?Carbon::parse($r->input('from'))->startOfDay():$to->copy()->subDays(29)->startOfDay(); if($from->gt($to)) abort(422,'from must be before to'); if($from->diffInDays($to)>366) abort(422,'maximum analytics range is 366 days'); return [$from,$to]; }
}
