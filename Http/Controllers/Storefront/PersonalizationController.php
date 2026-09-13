<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Http\Controllers\Controller;
use Modules\PriyasaCore\Services\CustomerEventService;
use Modules\PriyasaCore\Services\RecommendationService;
final class PersonalizationController extends Controller {
 public function recommendations(Request $request, RecommendationService $service){$userId=$request->user()?->getAuthIdentifier();$session=$request->header('X-Session-Id');$slot=(string)$request->query('slot','for_you');$limit=(int)$request->query('limit',12);return response()->json(['data'=>$service->forSlot($userId?(int)$userId:null,$session,$slot,$limit)]);}
 public function track(Request $request, CustomerEventService $events){$data=$request->validate(['event'=>'required|string|max:50','product_id'=>'nullable|integer','variant_id'=>'nullable|integer','query'=>'nullable|string|max:255','session_id'=>'nullable|string|max:120','metadata'=>'nullable|array']);$events->track($request,$data['event'],$data);return response()->json(['data'=>['tracked'=>true]]);}
 public function recentlyViewed(Request $request){$user=$request->user();$q=DB::connection('priyasa')->table('priyasa_customer_events')->where('event_type','product_view')->when($user,fn($x)=>$x->where('user_id',$user->getAuthIdentifier()))->when(!$user&&$request->header('X-Session-Id'),fn($x)=>$x->where('session_id',$request->header('X-Session-Id')))->whereNotNull('product_id')->orderByDesc('occurred_at')->limit(20)->pluck('product_id')->unique()->values();return response()->json(['data'=>$q]);}
}
