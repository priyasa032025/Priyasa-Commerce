<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\PriyasaCore\Models\Collection;
final class CollectionController extends Controller {
 public function index(Request $r){$q=Collection::query()->withCount('products')->orderByDesc('created_at'); if($s=trim((string)$r->query('search','')))$q->where(fn($x)=>$x->where('name','like',"%$s%")->orWhere('slug','like',"%$s%")); if($r->has('active'))$q->where('is_active',$r->boolean('active')); return response()->json(['success'=>true,'data'=>$q->paginate(min(max($r->integer('per_page',25),1),100))]);}
 public function show(Collection $collection){return response()->json(['success'=>true,'data'=>$collection->loadCount('products')->load('products:id,name,slug,status')]);}
 public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:255','slug'=>['required','string','max:255',Rule::unique('priyasa_collections','slug')],'description'=>'nullable|string','image_url'=>'nullable|url|max:2048','is_active'=>'sometimes|boolean','product_ids'=>'nullable|array','product_ids.*'=>'integer|exists:priyasa_products,id']); $ids=$d['product_ids']??[]; unset($d['product_ids']); $c=Collection::create($d); $c->products()->sync($ids); return response()->json(['success'=>true,'data'=>$c->loadCount('products')->load('products:id,name,slug,status')],201);}
 public function update(Request $r,Collection $collection){$d=$r->validate(['name'=>'required|string|max:255','slug'=>['required','string','max:255',Rule::unique('priyasa_collections','slug')->ignore($collection->id)],'description'=>'nullable|string','image_url'=>'nullable|url|max:2048','is_active'=>'sometimes|boolean','product_ids'=>'nullable|array','product_ids.*'=>'integer|exists:priyasa_products,id']); $ids=$d['product_ids']??null; unset($d['product_ids']); $collection->update($d); if(is_array($ids))$collection->products()->sync($ids); return response()->json(['success'=>true,'data'=>$collection->fresh()->loadCount('products')->load('products:id,name,slug,status')]);}
 public function destroy(Collection $collection){$collection->update(['is_active'=>false]); return response()->json(['success'=>true,'data'=>$collection->fresh()]);}
}
