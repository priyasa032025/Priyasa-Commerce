<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\PriyasaCore\Models\Inventory;
use Modules\PriyasaCore\Models\Product;
use Modules\PriyasaCore\Models\ProductVariant;
use Modules\PriyasaCore\Models\ProductAttribute;
use Modules\PriyasaCore\Models\ProductAttributeOption;
use Modules\PriyasaCore\Services\BulkCatalogService;
use Modules\PriyasaCore\Services\InventoryService;

final class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::query()->with(['category','productAttributes.options','variants.inventory','variants.attributeOptions.attribute'])->latest('id');
        if ($search=trim((string)$request->query('search'))) $q->where(fn($x)=>$x->where('name','like',"%{$search}%")->orWhere('slug','like',"%{$search}%")->orWhere('sku','like',"%{$search}%"));
        if ($status=trim((string)$request->query('status'))) $q->where('status',$status);
        return response()->json(['success'=>true,'data'=>$q->paginate(min(max((int)$request->query('per_page',50),1),100))]);
    }

    public function show(Product $product)
    {
        return response()->json(['success'=>true,'data'=>$product->load(['category','productAttributes.options','variants.inventory','variants.attributeOptions.attribute','media'])]);
    }

    public function store(Request $request)
    {
        $data=$this->productData($request);
        if (($data['status'] ?? 'draft') === 'published') $data['published_at'] = now();
        $product=Product::create($data);
        return response()->json(['success'=>true,'data'=>$product->load(['category','productAttributes.options','variants.inventory','variants.attributeOptions.attribute','media'])],201);
    }

    public function update(Request $request, Product $product)
    {
        $data=$this->productData($request,$product);
        if (($data['status'] ?? $product->status) === 'published' && $product->status !== 'published') $data['published_at']=now();
        if (($data['status'] ?? $product->status) !== 'published') $data['published_at']=null;
        $product->update($data);
        return response()->json(['success'=>true,'data'=>$product->fresh()->load(['category','productAttributes.options','variants.inventory','variants.attributeOptions.attribute','media'])]);
    }

    public function destroy(Product $product)
    {
        $product->update(['status'=>'archived','published_at'=>null]);
        $product->variants()->update(['is_active'=>false]);
        return response()->json(['success'=>true,'data'=>$product->fresh()]);
    }

    public function variantStore(Request $request, Product $product)
    {
        $data=$this->variantData($request);
        $inventoryData=['quantity'=>(int)($data['quantity']??0),'low_stock_threshold'=>(int)($data['low_stock_threshold']??3)];
        unset($data['quantity'],$data['low_stock_threshold']);
        $variant=DB::connection('priyasa')->transaction(function()use($product,$data,$inventoryData){$v=$product->variants()->create($data);$v->inventory()->create($inventoryData);return $v->load('inventory');});
        return response()->json(['success'=>true,'data'=>$variant],201);
    }

    public function variantUpdate(Request $request, Product $product, ProductVariant $variant, InventoryService $inventoryService)
    {
        abort_unless((int)$variant->product_id===(int)$product->id,404);
        $data=$this->variantData($request,$variant);
        $requestedQuantity=array_key_exists('quantity',$data)?(int)$data['quantity']:null;
        $threshold=array_key_exists('low_stock_threshold',$data)?(int)$data['low_stock_threshold']:null;
        unset($data['quantity'],$data['low_stock_threshold']);
        DB::connection('priyasa')->transaction(function()use($variant,$data,$requestedQuantity,$threshold,$inventoryService):void{
            $variant->update($data);
            $inv=$variant->inventory()->lockForUpdate()->firstOrCreate([],['quantity'=>0,'reserved_quantity'=>0,'low_stock_threshold'=>3]);
            if($threshold!==null)$inv->low_stock_threshold=$threshold;
            if($requestedQuantity!==null){$delta=$requestedQuantity-(int)$inv->quantity;if($delta!==0)$inventoryService->adjust($variant,$delta,'Admin variant quantity update');}
            else $inv->save();
        });
        return response()->json(['success'=>true,'data'=>$variant->fresh()->load('inventory')]);
    }

    public function variantDestroy(Product $product, ProductVariant $variant)
    {
        abort_unless((int)$variant->product_id===(int)$product->id,404);
        $variant->update(['is_active'=>false]);
        return response()->json(['success'=>true,'data'=>$variant->fresh()->load('inventory')]);
    }

    public function attributes(Product $product)
    {
        return response()->json(['success'=>true,'data'=>$product->load('productAttributes.options')->productAttributes]);
    }

    public function syncAttributes(Request $request, Product $product)
    {
        $data = $request->validate(['attribute_ids'=>'required|array|min:1','attribute_ids.*'=>'integer|exists:priyasa_attributes,id']);
        $ids = array_values(array_unique(array_map('intval', $data['attribute_ids'])));
        $product->productAttributes()->sync(array_combine($ids, array_map(fn($i)=>['sort_order'=>$i], array_keys($ids))));
        return response()->json(['success'=>true,'data'=>$product->fresh()->load('productAttributes.options')->productAttributes]);
    }

    public function attachVariantOptions(Request $request, Product $product, ProductVariant $variant)
    {
        abort_unless((int)$variant->product_id === (int)$product->id, 404);
        $data = $request->validate(['attribute_option_ids'=>'array','attribute_option_ids.*'=>'integer|exists:priyasa_attribute_options,id']);
        $ids = array_values(array_unique(array_map('intval', $data['attribute_option_ids'] ?? [])));
        if ($ids) {
            $valid = ProductAttributeOption::query()->whereIn('id',$ids)->whereHas('attribute.products', fn($q)=>$q->whereKey($product->id))->pluck('id')->all();
            abort_unless(count($valid) === count($ids), 422, 'One or more attribute options are not assigned to this product.');
        }
        $variant->attributeOptions()->sync($ids);
        return response()->json(['success'=>true,'data'=>$variant->fresh()->load('attributeOptions.attribute')]);
    }

    public function generateVariants(Request $request, Product $product)
    {
        $data = $request->validate([
            'attribute_ids'=>'required|array|min:1','attribute_ids.*'=>'integer|exists:priyasa_attributes,id',
            'base_sku'=>'nullable|string|max:40','price'=>'nullable|numeric|min:0','mrp'=>'nullable|numeric|min:0',
            'quantity'=>'nullable|integer|min:0','low_stock_threshold'=>'nullable|integer|min:0',
        ]);
        $attributes = ProductAttribute::query()->with(['options'=>fn($q)=>$q->where('is_active',true)->orderBy('sort_order')->orderBy('id')])->whereIn('id',$data['attribute_ids'])->where('is_active',true)->get();
        abort_unless($attributes->count() === count(array_unique($data['attribute_ids'])), 422, 'One or more attributes are invalid or inactive.');
        $sets = [[]];
        foreach ($attributes as $attribute) {
            $next=[];
            foreach ($sets as $partial) foreach ($attribute->options as $option) $next[] = array_merge($partial, [$option]);
            $sets=$next;
        }
        if (!$sets) return response()->json(['success'=>true,'data'=>[]]);
        $created=[];
        DB::connection('priyasa')->transaction(function() use($product,$sets,$data,&$created): void {
            foreach ($sets as $options) {
                $labels=array_map(fn($o)=>$o->label,$options); $suffix=strtoupper(implode('-',array_map(fn($x)=>preg_replace('/[^A-Z0-9]+/i','',str_replace(' ','-',$x)),$labels)));
                $sku=trim(($data['base_sku'] ?? ($product->sku ?: 'PRI-'.$product->id)).'-'.$suffix);
                $variant=ProductVariant::query()->firstOrCreate(['sku'=>$sku],['product_id'=>$product->id,'price'=>$data['price'] ?? $product->price,'mrp'=>$data['mrp'] ?? $product->mrp,'attributes'=>collect($options)->mapWithKeys(fn($o)=>[$o->attribute->slug=>$o->label])->all(),'is_active'=>true]);
                if ((int)$variant->product_id !== (int)$product->id) continue;
                $variant->attributeOptions()->sync(collect($options)->pluck('id')->all());
                $variant->inventory()->firstOrCreate([],['quantity'=>(int)($data['quantity']??0),'reserved_quantity'=>0,'low_stock_threshold'=>(int)($data['low_stock_threshold']??3)]);
                $created[]=$variant->load(['inventory','attributeOptions.attribute']);
            }
        });
        return response()->json(['success'=>true,'data'=>$created]);
    }

    public function bulkPrice(Request $request, BulkCatalogService $bulk)
    {
        $data = $request->validate([
            'product_ids'=>'nullable|array','product_ids.*'=>'integer|exists:priyasa_products,id',
            'variant_ids'=>'nullable|array','variant_ids.*'=>'integer|exists:priyasa_product_variants,id',
            'mrp'=>'nullable|array:operation,value','mrp.operation'=>'required_with:mrp|in:set,increase_amount,decrease_amount,increase_percentage,decrease_percentage','mrp.value'=>'required_with:mrp|numeric|min:0',
            'price'=>'nullable|array:operation,value','price.operation'=>'required_with:price|in:set,increase_amount,decrease_amount,increase_percentage,decrease_percentage','price.value'=>'required_with:price|numeric|min:0',
            'apply_to_variants'=>'nullable|boolean','reason'=>'nullable|string|max:255',
        ]);
        $updated=$bulk->prices($data, optional($request->user())->id);
        return response()->json(['success'=>true,'data'=>['updated'=>$updated]]);
    }

    private function productData(Request $request, ?Product $product=null): array
    {
        return $request->validate([
            'name'=>'required|string|max:255','slug'=>['required','string','max:255',Rule::unique('priyasa_products','slug')->ignore($product?->id)],
            'sku'=>['nullable','string','max:64',Rule::unique('priyasa_products','sku')->ignore($product?->id)],
            'price'=>'required|numeric|min:0','mrp'=>'required|numeric|min:0','cost_price'=>'nullable|numeric|min:0',
            'tax_rate'=>'nullable|numeric|min:0|max:100','gst_rate'=>'nullable|numeric|min:0|max:100','hsn_code'=>'nullable|string|max:32','tax_class'=>'nullable|string|max:64','unit'=>'nullable|string|max:32','country_of_origin'=>'nullable|string|size:2','manufacturer'=>'nullable|string|max:255','pack_of'=>'nullable|integer|min:1','currency'=>'nullable|string|size:3','brand'=>'nullable|string|max:255','brand_slug'=>'nullable|string|max:191',
            'status'=>'nullable|in:draft,published,archived','category_id'=>'nullable|integer|exists:priyasa_categories,id',
            'description'=>'nullable|string','short_description'=>'nullable|string','attributes'=>'nullable|array','media'=>'nullable|array',
        ]);
    }

    private function variantData(Request $request, ?ProductVariant $variant=null): array
    {
        return $request->validate([
            'sku'=>['required','string','max:64',Rule::unique('priyasa_product_variants','sku')->ignore($variant?->id)],
            'barcode'=>'nullable|string|max:255','size'=>'nullable|string|max:64','color'=>'nullable|string|max:64',
            'price'=>'nullable|numeric|min:0','mrp'=>'nullable|numeric|min:0','cost_price'=>'nullable|numeric|min:0','gst_rate'=>'nullable|numeric|min:0|max:100','hsn_code'=>'nullable|string|max:32','tax_class'=>'nullable|string|max:64','unit'=>'nullable|string|max:32','weight_grams'=>'nullable|numeric|min:0',
            'image_url'=>'nullable|url|max:2048','attributes'=>'nullable|array','is_active'=>'nullable|boolean',
            'quantity'=>'nullable|integer|min:0','low_stock_threshold'=>'nullable|integer|min:0',
        ]);
    }
}
