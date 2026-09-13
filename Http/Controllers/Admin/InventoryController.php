<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Inventory;
use Modules\PriyasaCore\Models\ProductVariant;
use Modules\PriyasaCore\Models\InventoryMovement;
use Modules\PriyasaCore\Services\InventoryService;

final class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $q = Inventory::query()->with(['variant.product','variant.attributeOptions.attribute'])->latest('id');
        if ($search=trim((string)$request->query('search'))) {
            $q->whereHas('variant', fn($v)=>$v->where('sku','like',"%{$search}%")->orWhereHas('product',fn($p)=>$p->where('name','like',"%{$search}%")));
        }
        if ($request->boolean('low_stock')) $q->whereColumn('quantity','>=','reserved_quantity')->whereRaw('(quantity - reserved_quantity) <= low_stock_threshold');
        if ($request->boolean('out_of_stock')) $q->whereRaw('(quantity - reserved_quantity) <= 0');
        return response()->json(['success'=>true,'data'=>$q->paginate(min(max((int)$request->query('per_page',50),1),200))]);
    }

    public function show(ProductVariant $variant)
    {
        return response()->json(['success'=>true,'data'=>$variant->load(['product','inventory','attributeOptions.attribute'])]);
    }

    public function adjust(Request $request, ProductVariant $variant, InventoryService $inventory)
    {
        $data = $request->validate(['delta'=>'required|integer','reason'=>'nullable|string|max:255']);
        $result = $inventory->adjust($variant, (int)$data['delta'], $data['reason'] ?? 'Admin adjustment');
        return response()->json(['success'=>true,'data'=>$result->load('variant.product')]);
    }

    public function setStock(Request $request, ProductVariant $variant, InventoryService $inventory)
    {
        $data=$request->validate(['quantity'=>'required|integer|min:0','low_stock_threshold'=>'nullable|integer|min:0','reason'=>'nullable|string|max:255']);
        $result=$inventory->setQuantity($variant,(int)$data['quantity'],(int)($data['low_stock_threshold']??3),$data['reason']??'Admin stock update');
        return response()->json(['success'=>true,'data'=>$result->load('variant.product')]);
    }

    public function bulkSetStock(Request $request, InventoryService $inventory)
    {
        $data=$request->validate(['items'=>'required|array|min:1','items.*.variant_id'=>'required|integer|exists:priyasa_product_variants,id','items.*.quantity'=>'required|integer|min:0','items.*.low_stock_threshold'=>'nullable|integer|min:0','reason'=>'nullable|string|max:255']);
        $updated=DB::connection('priyasa')->transaction(function() use($data,$inventory){$n=0;foreach($data['items'] as $item){$inventory->setQuantity(ProductVariant::findOrFail($item['variant_id']),(int)$item['quantity'],(int)($item['low_stock_threshold']??3),$data['reason']??'Bulk stock update');$n++;}return $n;});
        return response()->json(['success'=>true,'data'=>['updated'=>$updated]]);
    }

    public function movements(Request $request, ProductVariant $variant)
    {
        return response()->json(['success'=>true,'data'=>InventoryMovement::query()->where('variant_id',$variant->id)->latest('id')->paginate(min(max((int)$request->query('per_page',50),1),200))]);
    }
}
