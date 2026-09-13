<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Product;
use Modules\PriyasaCore\Models\ProductMedia;

class ProductMediaController extends Controller
{
    public function index(Product $product)
    {
        return response()->json(['success'=>true,'data'=>$product->media()->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request, Product $product)
    {
        $data=$request->validate([
            'type'=>'nullable|in:image,video', 'url'=>'required|url|max:2048',
            'alt_text'=>'nullable|string|max:255', 'sort_order'=>'nullable|integer|min:0', 'is_primary'=>'nullable|boolean',
            'variant_id'=>'nullable|integer|exists:priyasa_product_variants,id',
        ]);
        if (!empty($data['variant_id'])) abort_unless($product->variants()->whereKey($data['variant_id'])->exists(),422,'Variant does not belong to this product.');
        $media=DB::connection('priyasa')->transaction(function() use($product,$data){
            $makePrimary=(bool)($data['is_primary']??false);
            if($makePrimary) $product->media()->update(['is_primary'=>false]);
            if(!$product->media()->exists()) $makePrimary=true;
            return $product->media()->create(array_merge(['type'=>'image','sort_order'=>0,'is_primary'=>$makePrimary],$data));
        });
        return response()->json(['success'=>true,'data'=>$media],201);
    }

    public function update(Request $request, Product $product, ProductMedia $media)
    {
        abort_unless($media->product_id===$product->id,404);
        $data=$request->validate([
            'type'=>'sometimes|in:image,video','url'=>'sometimes|required|url|max:2048','alt_text'=>'nullable|string|max:255',
            'sort_order'=>'sometimes|integer|min:0','is_primary'=>'sometimes|boolean','variant_id'=>'nullable|integer|exists:priyasa_product_variants,id',
        ]);
        if(array_key_exists('variant_id',$data)&&$data['variant_id']!==null) abort_unless($product->variants()->whereKey($data['variant_id'])->exists(),422,'Variant does not belong to this product.');
        $media=DB::connection('priyasa')->transaction(function() use($product,$media,$data){
            if(!empty($data['is_primary'])) $product->media()->where('id','!=',$media->id)->update(['is_primary'=>false]);
            $media->update($data);
            return $media->fresh();
        });
        return response()->json(['success'=>true,'data'=>$media]);
    }

    public function destroy(Product $product, ProductMedia $media)
    {
        abort_unless($media->product_id===$product->id,404);
        DB::connection('priyasa')->transaction(function() use($product,$media){
            $wasPrimary=$media->is_primary; $media->delete();
            if($wasPrimary){ $next=$product->media()->orderBy('sort_order')->orderBy('id')->first(); if($next) $next->update(['is_primary'=>true]); }
        });
        return response()->json(['success'=>true]);
    }
}
