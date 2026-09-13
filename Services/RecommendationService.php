<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PriyasaCore\Models\Product;
final class RecommendationService {
 public function forSlot(?int $userId, ?string $sessionId, string $slot='for_you', int $limit=12): array {
  $limit=max(1,min(40,$limit)); $cached=DB::connection('priyasa')->table('priyasa_recommendation_cache')->where('slot',$slot)->where('expires_at','>',now())->when($userId,fn($q)=>$q->where('user_id',$userId))->when(!$userId&&$sessionId,fn($q)=>$q->where('session_id',$sessionId))->latest('id')->first();
  if($cached) return $this->products(json_decode($cached->product_ids,true) ?: [],$limit);
  $ids=$this->rank($userId,$sessionId,$slot,$limit); if($ids) DB::connection('priyasa')->table('priyasa_recommendation_cache')->insert(['user_id'=>$userId,'session_id'=>$sessionId,'slot'=>$slot,'product_ids'=>json_encode($ids),'expires_at'=>now()->addMinutes(15),'created_at'=>now(),'updated_at'=>now()]);
  return $this->products($ids,$limit);
 }
 private function rank(?int $userId, ?string $sessionId,string $slot,int $limit): array {
  $recent=DB::connection('priyasa')->table('priyasa_customer_events')->whereIn('event_type',['product_view','wishlist_add','cart_add','purchase'])->when($userId,fn($q)=>$q->where('user_id',$userId))->when(!$userId&&$sessionId,fn($q)=>$q->where('session_id',$sessionId))->latest('occurred_at')->limit(30)->pluck('product_id')->filter()->unique()->values()->all();
  $scores=[]; foreach($recent as $pid){$related=DB::connection('priyasa')->table('priyasa_product_affinities')->where('product_id',$pid)->orderByDesc('score')->limit(20)->get(); foreach($related as $r)$scores[$r->related_product_id]=($scores[$r->related_product_id]??0)+(float)$r->score;}
  arsort($scores); $ids=array_slice(array_keys($scores),0,$limit); if(count($ids)<$limit){$fallback=Product::query()->where('is_active',true)->when($ids,fn($q)=>$q->whereNotIn('id',$ids))->orderByDesc('merchandising_score')->orderByDesc('id')->limit($limit-count($ids))->pluck('id')->all();$ids=array_merge($ids,$fallback);} return array_map('intval',$ids);
 }
 private function products(array $ids,int $limit): array { if(!$ids)return []; $rows=Product::query()->whereIn('id',$ids)->where('is_active',true)->with(['variants','category'])->get()->keyBy('id'); return collect($ids)->map(fn($id)=>$rows->get($id))->filter()->take($limit)->values()->map(fn($p)=>$p->toArray())->all(); }
}
