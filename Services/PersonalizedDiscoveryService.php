<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PersonalizedDiscoveryService
{
    public function home(?int $customerId, ?string $sessionId, int $limit = 12): array
    {
        $limit = min(48, max(1, $limit));
        $sections = [];
        $recent = $this->recentlyViewed($customerId, $sessionId, $limit);
        if ($recent) $sections[] = ['key'=>'recently_viewed','title'=>'Recently viewed','items'=>$this->products($recent, $limit)];

        foreach ([
            ['similar','Because you viewed these', $this->relationIds($recent,'similar',$limit)],
            ['frequently_bought','Frequently bought together', $this->relationIds($recent,'frequently_bought',$limit)],
            ['complete_the_look','Complete the look', $this->relationIds($recent,'complete_the_look',$limit)],
        ] as [$key,$title,$ids]) if ($ids) $sections[]=['key'=>$key,'title'=>$title,'items'=>$this->products($ids,$limit)];

        $affinity = $this->affinityIds($customerId, $limit);
        if ($affinity) $sections[]=['key'=>'personalized','title'=>'You may also like','items'=>$this->products($affinity,$limit)];

        if (!$sections) {
            $fallback = $this->fallback($limit);
            if ($fallback) $sections[]=['key'=>'trending','title'=>'Trending for you','items'=>$fallback];
        }
        return ['sections'=>$sections,'personalized'=>(bool)($customerId || $sessionId),'strategy'=>$customerId?'customer_affinity':'session_behavior'];
    }

    public function recommendations(?int $customerId, ?string $sessionId, ?int $productId, int $limit=12): array
    {
        $ids = [];
        if ($productId) {
            $ids = array_merge($this->relationIds([$productId], 'similar', $limit), $this->relationIds([$productId], 'complete_the_look', $limit));
        }
        if (!$ids) $ids = $this->affinityIds($customerId, $limit);
        if (!$ids) $ids = $this->recentlyViewed($customerId, $sessionId, $limit);
        $items = $this->products(array_values(array_unique($ids)), $limit);
        if (!$items) $items = $this->fallback($limit);
        return ['items'=>$items,'context_product_id'=>$productId,'personalized'=>(bool)($customerId || $sessionId)];
    }

    private function recentlyViewed(?int $customerId, ?string $sessionId, int $limit): array
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_customer_events')) return [];
        $q=DB::connection('priyasa')->table('priyasa_customer_events')->whereIn('event_type',['view_product','product_view','view']);
        if ($customerId) $q->where('customer_id',$customerId); elseif ($sessionId) $q->where('session_id',$sessionId); else return [];
        $column=Schema::connection('priyasa')->hasColumn('priyasa_customer_events','product_id')?'product_id':(Schema::connection('priyasa')->hasColumn('priyasa_customer_events','entity_id')?'entity_id':null);
        if (!$column) return [];
        return array_values(array_unique(array_filter($q->orderByDesc('created_at')->limit($limit*3)->pluck($column)->map(fn($v)=>(int)$v)->all())));
    }

    private function affinityIds(?int $customerId, int $limit): array
    {
        if (!$customerId || !Schema::connection('priyasa')->hasTable('priyasa_product_affinities')) return [];
        $cols=Schema::connection('priyasa')->getColumnListing('priyasa_product_affinities');
        $product=in_array('product_id',$cols,true)?'product_id':(in_array('variant_id',$cols,true)?'variant_id':null);
        if (!$product || !in_array('customer_id',$cols,true)) return [];
        $score=in_array('score',$cols,true)?'score':(in_array('weight',$cols,true)?'weight':null);
        $q=DB::connection('priyasa')->table('priyasa_product_affinities')->where('customer_id',$customerId)->when($score,fn($x)=>$x->orderByDesc($score))->limit($limit);
        return array_values(array_unique(array_map('intval',$q->pluck($product)->all())));
    }

    private function relationIds(array $sourceIds,string $type,int $limit): array
    {
        if (!$sourceIds || !Schema::connection('priyasa')->hasTable('priyasa_product_relations')) return [];
        $cols=Schema::connection('priyasa')->getColumnListing('priyasa_product_relations');
        $source=in_array('source_product_id',$cols,true)?'source_product_id':(in_array('product_id',$cols,true)?'product_id':null);
        $target=in_array('target_product_id',$cols,true)?'target_product_id':(in_array('related_product_id',$cols,true)?'related_product_id':null);
        if (!$source || !$target || !in_array('relation_type',$cols,true)) return [];
        $q=DB::connection('priyasa')->table('priyasa_product_relations')->whereIn($source,$sourceIds)->where('relation_type',$type);
        if (in_array('score',$cols,true)) $q->orderByDesc('score');
        elseif (in_array('weight',$cols,true)) $q->orderByDesc('weight');
        return array_values(array_unique(array_map('intval',$q->limit($limit)->pluck($target)->all())));
    }

    private function products(array $ids,int $limit): array
    {
        if (!$ids || !Schema::connection('priyasa')->hasTable('priyasa_products')) return [];
        $cols=Schema::connection('priyasa')->getColumnListing('priyasa_products');
        $select=array_values(array_filter(['id','slug','name','brand_slug','price','mrp','media','badge','is_featured'],fn($c)=>in_array($c,$cols,true)));
        if (!$select) return [];
        $rows=DB::connection('priyasa')->table('priyasa_products')->select($select)->whereIn('id',array_slice($ids,0,$limit));
        if (in_array('is_active',$cols,true)) $rows->where('is_active',true);
        $byId=$rows->get()->keyBy('id'); $out=[];
        foreach ($ids as $id) if (isset($byId[$id])) { $r=(array)$byId[$id]; $price=isset($r['price'])?(float)$r['price']:null; $mrp=isset($r['mrp'])?(float)$r['mrp']:null; $out[]=['id'=>$r['id'],'slug'=>$r['slug']??null,'name'=>$r['name']??null,'brand'=>$r['brand_slug']??null,'price'=>$price,'mrp'=>$mrp,'discount_percent'=>$mrp>0&&$price!==null?round(max(0,($mrp-$price)*100/$mrp),2):0,'badge'=>$r['badge']??null,'is_featured'=>(bool)($r['is_featured']??false)]; if(count($out)>=$limit) break; }
        return $out;
    }

    private function fallback(int $limit): array
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_products')) return [];
        $cols=Schema::connection('priyasa')->getColumnListing('priyasa_products'); $q=DB::connection('priyasa')->table('priyasa_products');
        if (in_array('is_active',$cols,true)) $q->where('is_active',true);
        if (in_array('merchandising_score',$cols,true)) $q->orderByDesc('merchandising_score'); elseif (in_array('is_featured',$cols,true)) $q->orderByDesc('is_featured');
        return $this->products($q->limit($limit)->pluck('id')->all(),$limit);
    }
}
