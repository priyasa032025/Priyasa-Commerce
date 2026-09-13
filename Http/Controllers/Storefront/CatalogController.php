<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\PriyasaCore\Models\Category;
use Modules\PriyasaCore\Models\Collection;
use Modules\PriyasaCore\Models\Product;

final class CatalogController extends Controller
{
    public function products(Request $request)
    {
        $perPage=min(100,max(1,(int)$request->query('per_page',config('priyasacore.per_page',24))));
        $query=Product::query()->with(['category','media','variants.inventory'])
            ->withCount(['reviews as approved_reviews_count'=>fn($q)=>$q->where('status','approved')])
            ->withAvg(['reviews as approved_rating'=>fn($q)=>$q->where('status','approved')],'rating')->published();
        if($search=trim((string)$request->query('search'))){$like='%'.addcslashes($search,'%_\\').'%';$query->where(fn($q)=>$q->where('name','like',$like)->orWhere('brand','like',$like)->orWhere('sku',$search)->orWhere('slug',$search));}
        if($category=trim((string)$request->query('category')))$query->whereHas('category',fn($q)=>$q->where('slug',$category));
        if($collection=trim((string)$request->query('collection')))$query->whereHas('collections',fn($q)=>$q->where('slug',$collection)->where('is_active',true));
        if($brand=trim((string)$request->query('brand')))$query->where('brand',$brand);
        if($request->boolean('featured'))$query->where('is_featured',true);
        if($request->filled('min_price'))$query->where('price','>=',(float)$request->query('min_price'));
        if($request->filled('max_price'))$query->where('price','<=',(float)$request->query('max_price'));
        if($request->boolean('in_stock'))$query->whereHas('variants.inventory',fn($q)=>$q->whereColumn('quantity','>','reserved_quantity'));
        if($request->boolean('sale_only'))$query->whereColumn('mrp','>','price')->where('price','>',0);
        $sort=(string)$request->query('sort','newest');
        if($sort==='popular'){
            $query->orderByDesc(\Illuminate\Support\Facades\DB::connection('priyasa')->raw("(SELECT COALESCE(SUM(oi.quantity),0) FROM priyasa_order_items oi INNER JOIN priyasa_orders o ON o.id=oi.order_id INNER JOIN priyasa_product_variants pv ON pv.id=oi.variant_id WHERE pv.product_id=priyasa_products.id AND o.status NOT IN ('cancelled','failed'))"))->orderByDesc('id');
        } elseif($sort==='discount'){
            $query->orderByRaw('CASE WHEN mrp > 0 THEN ((mrp-price)/mrp) ELSE 0 END DESC')->orderByDesc('id');
        } elseif($sort==='featured'){ $query->orderByDesc('is_featured')->orderByDesc('merchandising_score')->orderBy('sort_order')->orderByDesc('id');
        } elseif($sort==='relevance'){ $query->orderByDesc('merchandising_score')->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('id');
        } else { match($sort){ 'price_asc'=>$query->orderBy('price'), 'price_desc'=>$query->orderByDesc('price'), 'name_asc'=>$query->orderBy('name'), 'rating'=>$query->orderByDesc('approved_rating')->orderByDesc('id'), default=>$query->latest('id') }; }
        $page=$query->paginate($perPage)->withQueryString();
        return response()->json(['success'=>true,'data'=>['data'=>collect($page->items())->map(fn(Product $p)=>$this->contract($p))->values(),'meta'=>['current_page'=>$page->currentPage(),'per_page'=>$page->perPage(),'total'=>$page->total(),'last_page'=>$page->lastPage()]]]);
    }

    public function show(Product $product)
    {
        abort_unless($product->status==='published' && (!$product->published_at || $product->published_at->lte(now())),404);
        $product->load(['category','media','variants.inventory','collections']);
        $product->loadCount(['reviews as approved_reviews_count'=>fn($q)=>$q->where('status','approved')]);
        $product->loadAvg(['reviews as approved_rating'=>fn($q)=>$q->where('status','approved')],'rating');
        $related=Product::query()->published()->where('id','!=',$product->id)->when($product->category_id,fn($q)=>$q->where('category_id',$product->category_id))->with(['media','variants.inventory'])->latest('id')->limit(8)->get()->map(fn(Product $p)=>$this->contract($p));
        return response()->json(['success'=>true,'data'=>['product'=>$this->contract($product),'related_products'=>$related->values()]]);
    }

    public function categories(){return response()->json(['success'=>true,'data'=>Category::query()->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get()]);}
    public function collections(Request $request){$collections=Collection::query()->where('is_active',true)->orderByDesc('id')->get();if($request->boolean('with_products'))$collections->load(['products'=>fn($q)=>$q->published()->with(['media','variants.inventory'])->limit(12)]);return response()->json(['success'=>true,'data'=>$collections]);}
    public function collection(string $collection){$item=Collection::query()->where('slug',$collection)->where('is_active',true)->firstOrFail();$products=$item->products()->published()->with(['category','media','variants.inventory'])->paginate(config('priyasacore.per_page',24));$products->setCollection($products->getCollection()->map(fn(Product $p)=>$this->contract($p)));return response()->json(['success'=>true,'data'=>['collection'=>$item,'products'=>$products]]);}

    private function contract(Product $product): array
    {
        $attributes=is_array($product->attributes)?$product->attributes:[];
        $brandName=trim((string)($product->brand ?: data_get($attributes,'values.Brand') ?: data_get($attributes,'values.brand') ?: ''));
        $category=$product->category;
        $media=$product->relationLoaded('media') ? $product->getRelation('media') : collect();
        if($media->isEmpty() && is_array($product->getRawOriginal('media') ? json_decode($product->getRawOriginal('media'),true) : null)){
            $legacy=(array)json_decode($product->getRawOriginal('media'),true); foreach($legacy as $i=>$url){if(is_string($url)&&$url!=='')$media->push((object)['url'=>$url,'type'=>'image','sort_order'=>$i+1,'is_primary'=>$i===0]);}
        }
        $variants=$product->relationLoaded('variants') ? $product->getRelation('variants') : collect();
        $mrp=(float)$product->mrp; $price=(float)$product->price;
        $discount=$mrp>0 ? (int)round(max(0,($mrp-$price)/$mrp*100)) : 0;
        $badges=is_array(data_get($attributes,'badges')) ? array_values(array_map('strval',data_get($attributes,'badges'))) : [];
        if($discount>0 && !collect($badges)->contains(fn($b)=>str_contains($b,'%'))) $badges[]=$discount.'% OFF';
        return [
            'id'=>(int)$product->id,'name'=>(string)$product->name,'slug'=>(string)$product->slug,'sku'=>$product->sku,
            'brand'=>$brandName!==''?['id'=>(int)(data_get($attributes,'brand_id') ?: ($brandName==='PRIYASA'?1:0)),'name'=>$brandName,'slug'=>Str::slug($brandName)]:null,
            'category'=>$category?['id'=>(int)$category->id,'name'=>(string)$category->name,'slug'=>(string)$category->slug]:null,
            'pricing'=>['mrp'=>$mrp,'selling_price'=>$price,'discount_percent'=>$discount,'currency'=>(string)($product->currency?:'INR')],
            'media'=>$media->sortBy('sort_order')->values()->map(fn($m)=>['url'=>(string)$m->url,'type'=>(string)($m->type??'image'),'position'=>(int)($m->sort_order??1)])->values()->all(),
            'variants'=>$variants->filter(fn($v)=>$v->is_active)->values()->map(function($v){$inv=$v->inventory; $available=max(0,(int)($inv?->quantity??0)-(int)($inv?->reserved_quantity??0)); return ['id'=>(int)$v->id,'sku'=>(string)$v->sku,'size'=>$v->size,'color'=>$v->color,'price'=>(float)($v->price??0),'mrp'=>(float)($v->mrp??0),'inventory'=>['available'=>$available,'in_stock'=>$available>0]];})->all(),
            'rating'=>['average'=>round((float)($product->approved_rating??0),2),'count'=>(int)($product->approved_reviews_count??0)],
            'badges'=>array_values(array_unique(array_filter(array_merge($badges, $product->badge ? [(string)$product->badge] : [])))),
            'availability'=>['in_stock'=>$variants->contains(fn($v)=>max(0,(int)($v->inventory?->quantity??0)-(int)($v->inventory?->reserved_quantity??0))>0)],
        ];
    }
}
