<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Product;
use Modules\PriyasaCore\Models\Collection;
final class MerchandisingController extends Controller {
 public function products(Request $r){
  $q=Product::query()->select(['id','name','slug','sku','brand','status','price','mrp','is_featured','merchandising_score','sort_order','badge'])->orderBy('sort_order')->orderByDesc('merchandising_score');
  if($s=trim((string)$r->query('search','')))$q->where(fn($x)=>$x->where('name','like',"%$s%")->orWhere('sku','like',"%$s%")->orWhere('brand','like',"%$s%"));
  if($r->filled('featured'))$q->where('is_featured',$r->boolean('featured'));
  return response()->json(['success'=>true,'data'=>$q->paginate(min(max((int)$r->query('per_page',50),1),100))]);
 }
 public function update(Request $r, Product $product){
  $d=$r->validate(['is_featured'=>'sometimes|boolean','merchandising_score'=>'sometimes|numeric|min:-1000|max:1000','sort_order'=>'sometimes|integer|min:0','badge'=>'nullable|string|max:80','search_keywords'=>'nullable|string|max:10000','meta_title'=>'nullable|string|max:255','meta_description'=>'nullable|string|max:10000','canonical_url'=>'nullable|url|max:2048']);
  $product->update($d); return response()->json(['success'=>true,'data'=>$product->fresh()]);
 }
 public function reorder(Request $r){
  $d=$r->validate(['products'=>'required|array|min:1','products.*.id'=>'required|integer|exists:priyasa_products,id','products.*.sort_order'=>'required|integer|min:0']);
  DB::connection('priyasa')->transaction(fn()=>collect($d['products'])->each(fn($x)=>Product::whereKey($x['id'])->update(['sort_order'=>$x['sort_order']])));
  return response()->json(['success'=>true,'data'=>Product::query()->orderBy('sort_order')->orderByDesc('merchandising_score')->limit(200)->get(['id','name','sort_order','merchandising_score','is_featured'])]);
 }
 public function collectionReorder(Request $r, Collection $collection){
  $d=$r->validate(['products'=>'required|array|min:1','products.*.id'=>'required|integer|exists:priyasa_products,id','products.*.sort_order'=>'required|integer|min:0']);
  $ids=collect($d['products'])->pluck('id')->all();
  $valid=$collection->products()->whereIn('priyasa_products.id',$ids)->pluck('priyasa_products.id')->all();
  DB::connection('priyasa')->transaction(fn()=>collect($d['products'])->filter(fn($x)=>in_array($x['id'],$valid,true))->each(fn($x)=>$collection->products()->updateExistingPivot($x['id'],['sort_order'=>$x['sort_order']])));
  return response()->json(['success'=>true,'data'=>$collection->load('products:id,name,slug')]);
 }
}
