<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Http\Controllers\Controller;
use Modules\PriyasaCore\Services\SearchIntelligenceService;
final class SearchIntelligenceController extends Controller {
 public function search(Request $r, SearchIntelligenceService $s){$data=$r->validate(['q'=>'required|string|max:255','limit'=>'nullable|integer|min:1|max:50','page'=>'nullable|integer|min:1']); return response()->json(['success'=>true,'data'=>$s->search($data['q'],(int)($data['limit']??20),(int)($data['page']??1),$r->user()?->getAuthIdentifier(),$r->header('X-Session-Id'))]);}
 public function suggestions(Request $r, SearchIntelligenceService $s){$q=(string)$r->query('q',''); return response()->json(['success'=>true,'data'=>$s->suggestions($q,(int)$r->query('limit',8))]);}
 public function trending(Request $r, SearchIntelligenceService $s){return response()->json(['success'=>true,'data'=>$s->trending((int)$r->query('limit',12))]);}
 public function related(Request $r, SearchIntelligenceService $s,int $product){$type=(string)$r->query('type','similar'); if(!in_array($type,['similar','frequently_bought','complete_the_look'],true))$type='similar'; return response()->json(['success'=>true,'data'=>$s->related($product,$type,(int)$r->query('limit',12))]);}
}
