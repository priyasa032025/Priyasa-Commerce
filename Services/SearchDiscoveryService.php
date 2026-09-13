<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SearchDiscoveryService
{
    public function search(array $input): array
    {
        $q = trim((string)($input['q'] ?? ''));
        $page = max(1, (int)($input['page'] ?? 1));
        $perPage = min(48, max(1, (int)($input['per_page'] ?? 24)));
        $sort = (string)($input['sort'] ?? 'relevance');

        if ($q !== '') $this->recordQuery($q);

        $table = 'priyasa_products';
        if (!Schema::connection('priyasa')->hasTable($table)) return ['items'=>[], 'facets'=>[], 'pagination'=>$this->pagination(0,$page,$perPage), 'query'=>$q];

        $columns = Schema::connection('priyasa')->getColumnListing($table);
        $has = static fn(string $c): bool => in_array($c, $columns, true);
        $select = ['id'];
        foreach (['slug','name','description','brand_slug','category_id','price','mrp','search_keywords','merchandising_score','sort_order','is_featured','badge','meta_title','meta_description','canonical_url','created_at'] as $c) if ($has($c)) $select[]=$c;

        $query = DB::connection('priyasa')->table($table)->select($select);
        if ($has('is_active')) $query->where('is_active', true);
        if ($has('status')) $query->whereIn('status', ['active','published']);

        if ($q !== '') {
            $terms = preg_split('/\s+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $query->where(function($w) use ($terms,$has) {
                foreach ($terms as $term) {
                    $like = '%'.addcslashes($term, '%_\\').'%';
                    $w->where(function($x) use ($like,$has) {
                        $first=true;
                        foreach (['name','slug','brand_slug','description','search_keywords'] as $c) if ($has($c)) {
                            if ($first) { $x->where($c,'like',$like); $first=false; } else $x->orWhere($c,'like',$like);
                        }
                    });
                }
            });
        }

        $this->applyFilters($query, $input, $has);
        $this->applySort($query, $sort, $has);

        $total = (clone $query)->count();
        $rows = $query->forPage($page,$perPage)->get();
        $items = $rows->map(fn($r)=>$this->card($r))->values()->all();

        return ['items'=>$items,'facets'=>$this->facets($input,$has),'pagination'=>$this->pagination($total,$page,$perPage),'query'=>$q,'sort'=>$sort];
    }

    public function suggestions(string $q, int $limit = 8): array
    {
        $q=trim($q); if ($q==='') return [];
        $limit=min(20,max(1,$limit));
        $out=[];
        if (Schema::connection('priyasa')->hasTable('priyasa_search_synonyms')) {
            $rows=DB::connection('priyasa')->table('priyasa_search_synonyms')->where('term','like','%'.$q.'%')->limit($limit)->get();
            foreach($rows as $r) $out[]=['type'=>'synonym','text'=>(string)($r->replacement ?? $r->synonym ?? $r->term)];
        }
        if (Schema::connection('priyasa')->hasTable('priyasa_products')) {
            $cols=Schema::connection('priyasa')->getColumnListing('priyasa_products');
            if(in_array('name',$cols,true)) foreach(DB::connection('priyasa')->table('priyasa_products')->where('name','like','%'.$q.'%')->distinct()->limit($limit)->pluck('name') as $name) $out[]=['type'=>'product','text'=>$name];
        }
        return collect($out)->unique('text')->take($limit)->values()->all();
    }

    public function trending(int $limit=10): array
    {
        if (!Schema::connection('priyasa')->hasTable('priyasa_search_queries')) return [];
        $q=DB::connection('priyasa')->table('priyasa_search_queries');
        $cols=Schema::connection('priyasa')->getColumnListing('priyasa_search_queries');
        $term=in_array('query',$cols,true)?'query':(in_array('normalized_query',$cols,true)?'normalized_query':null);
        if(!$term) return [];
        $count=in_array('count',$cols,true)?'count':(in_array('search_count',$cols,true)?'search_count':null);
        $rows=$q->select($term)->when($count,fn($x)=>$x->selectRaw('SUM('.$count.') as popularity'))->groupBy($term)->orderByDesc($count?'popularity':$term)->limit(min(50,max(1,$limit)))->get();
        return $rows->map(fn($r)=>['query'=>$r->{$term},'popularity'=>$count?(int)$r->popularity:null])->all();
    }

    private function recordQuery(string $q): void
    {
        if(!Schema::connection('priyasa')->hasTable('priyasa_search_queries')) return;
        $cols=Schema::connection('priyasa')->getColumnListing('priyasa_search_queries'); $now=now();
        $data=[];
        foreach(['query','normalized_query'] as $c) if(in_array($c,$cols,true)) $data[$c]=mb_strtolower($q);
        if(in_array('search_count',$cols,true)) $data['search_count']=1;
        if(in_array('count',$cols,true)) $data['count']=1;
        if(in_array('created_at',$cols,true)) $data['created_at']=$now;
        if(in_array('updated_at',$cols,true)) $data['updated_at']=$now;
        if($data) DB::connection('priyasa')->table('priyasa_search_queries')->insert($data);
    }

    private function applyFilters($query,array $input,callable $has): void
    {
        if($has('brand_slug') && !empty($input['brands'])) $query->whereIn('brand_slug',(array)$input['brands']);
        if($has('category_id') && !empty($input['categories'])) $query->whereIn('category_id',(array)$input['categories']);
        if($has('price')) { if(isset($input['min_price'])) $query->where('price','>=',(float)$input['min_price']); if(isset($input['max_price'])) $query->where('price','<=',(float)$input['max_price']); }
    }

    private function applySort($query,string $sort,callable $has): void
    {
        if($sort==='price_asc' && $has('price')) $query->orderBy('price');
        elseif($sort==='price_desc' && $has('price')) $query->orderByDesc('price');
        elseif($sort==='newest' && $has('created_at')) $query->orderByDesc('created_at');
        elseif($sort==='featured' && $has('is_featured')) $query->orderByDesc('is_featured')->when($has('sort_order'),fn($q)=>$q->orderBy('sort_order'));
        elseif($sort==='popularity' && $has('merchandising_score')) $query->orderByDesc('merchandising_score');
        else { if($has('merchandising_score')) $query->orderByDesc('merchandising_score'); if($has('is_featured')) $query->orderByDesc('is_featured'); if($has('sort_order')) $query->orderBy('sort_order'); }
    }

    private function facets(array $input,callable $has): array
    {
        if(!Schema::connection('priyasa')->hasTable('priyasa_products')) return [];
        $out=[];
        if($has('brand_slug')) $out['brands']=DB::connection('priyasa')->table('priyasa_products')->whereNotNull('brand_slug')->where('brand_slug','<>','')->select('brand_slug')->distinct()->orderBy('brand_slug')->limit(200)->pluck('brand_slug')->values()->all();
        if($has('category_id')) $out['categories']=DB::connection('priyasa')->table('priyasa_products')->whereNotNull('category_id')->select('category_id')->distinct()->orderBy('category_id')->limit(200)->pluck('category_id')->values()->all();
        return $out;
    }

    private function card($r): array
    {
        $a=(array)$r; $price=isset($a['price'])?(float)$a['price']:null; $mrp=isset($a['mrp'])?(float)$a['mrp']:null;
        return ['id'=>$a['id']??null,'slug'=>$a['slug']??null,'name'=>$a['name']??null,'brand'=>$a['brand_slug']??null,'price'=>$price,'mrp'=>$mrp,'discount_percent'=>$mrp && $price!==null?round(max(0,($mrp-$price)*100/$mrp),2):0,'badge'=>$a['badge']??null,'is_featured'=>(bool)($a['is_featured']??false)];
    }

    private function pagination(int $total,int $page,int $perPage): array { return ['page'=>$page,'per_page'=>$perPage,'total'=>$total,'last_page'=>$total?((int)ceil($total/$perPage)):1,'has_more'=>$page*$perPage<$total]; }
}
