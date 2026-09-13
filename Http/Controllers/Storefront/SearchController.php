<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Storefront;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Product;
final class SearchController extends Controller {
 public function search(Request $r) {
  $term=trim((string)$r->query('q',''));
  if($term==='') return response()->json(['success'=>true,'data'=>['items'=>[],'facets'=>['brands'=>[],'categories'=>[]],'meta'=>['total'=>0]]]);
  $like='%'.addcslashes($term,'%_\\').'%';
  $base=Product::query()->published()->where(fn($q)=>$q->where('name','like',$like)->orWhere('brand','like',$like)->orWhere('search_keywords','like',$like)->orWhere('sku',$term)->orWhere('slug',$term));
  $total=(clone $base)->count();
  $brands=(clone $base)->whereNotNull('brand')->where('brand','!=','')->select('brand',DB::connection('priyasa')->raw('count(*) as count'))->groupBy('brand')->orderByDesc('count')->limit(20)->get();
  $categories=(clone $base)->whereNotNull('category_id')->with('category:id,name,slug')->select('category_id')->groupBy('category_id')->limit(20)->get()->map(fn($x)=>['id'=>$x->category_id,'name'=>$x->category?->name,'slug'=>$x->category?->slug]);
  $items=(clone $base)->with(['category','media','variants.inventory'])->orderByDesc('merchandising_score')->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('id')->limit(min(max((int)$r->query('limit',12),1),50))->get();
  $catalog=new CatalogController();
  $ref=new \ReflectionMethod($catalog,'contract'); $ref->setAccessible(true);
  return response()->json(['success'=>true,'data'=>['items'=>$items->map(fn($p)=>$ref->invoke($catalog,$p))->values(),'facets'=>['brands'=>$brands,'categories'=>$categories],'meta'=>['total'=>$total,'query'=>$term]]]);
 }
 public function suggestions(Request $r) {
  $term=trim((string)$r->query('q','')); if(strlen($term)<2)return response()->json(['success'=>true,'data'=>[]]);
  $like=addcslashes($term,'%_\\').'%';
  $products=Product::query()->published()->where(fn($q)=>$q->where('name','like',$like)->orWhere('brand','like',$like))->orderByDesc('is_featured')->orderByDesc('merchandising_score')->limit(8)->get(['id','name','slug','brand']);
  return response()->json(['success'=>true,'data'=>$products]);
 }
}
