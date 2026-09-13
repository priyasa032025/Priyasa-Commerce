<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Product;
use Modules\PriyasaCore\Models\ProductVariant;

final class ProductExperienceService
{
    public function detail(Product $product, ?string $pincode = null): array
    {
        $variants = ProductVariant::query()->where('product_id', $product->id)->get();
        $variantIds = $variants->pluck('id')->all();
        $stock = $this->stock($variantIds, $pincode);

        $media = [];
        $relation = method_exists($product, 'getRelation') ? $product->getRelation('media') : null;
        if ($relation) {
            foreach ($relation as $m) $media[] = is_object($m) && method_exists($m, 'toArray') ? $m->toArray() : (array)$m;
        } elseif (is_array($product->media ?? null)) {
            $media = $product->media;
        }

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => $product->brand_slug ?? null,
            'description' => $product->description ?? null,
            'seo' => [
                'title' => $product->meta_title ?? null,
                'description' => $product->meta_description ?? null,
                'canonical_url' => $product->canonical_url ?? null,
            ],
            'badges' => array_values(array_filter([$product->badge ?? null, !empty($product->is_featured) ? 'featured' : null])),
            'media' => $media,
            'variants' => $variants->map(function ($v) use ($stock) {
                $s = $stock[(string)$v->id] ?? ['quantity' => 0, 'available' => 0];
                $mrp = (float)($v->mrp ?? $v->price ?? 0);
                $price = (float)($v->price ?? 0);
                return [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'size' => $v->size ?? null,
                    'color' => $v->color ?? null,
                    'attributes' => $v->attributes ?? [],
                    'price' => $price,
                    'mrp' => $mrp,
                    'discount_percent' => $mrp > 0 ? round(max(0, ($mrp - $price) * 100 / $mrp), 2) : 0,
                    'gst_rate' => $v->gst_rate ?? null,
                    'hsn_code' => $v->hsn_code ?? null,
                    'stock' => $s,
                ];
            })->values()->all(),
            'delivery' => $this->delivery($pincode, $variantIds),
        ];
    }

    public function availability(int $variantId, ?string $pincode = null): array
    {
        $stock = $this->stock([$variantId], $pincode);
        return ['variant_id' => $variantId, 'pincode' => $pincode, 'stock' => $stock[(string)$variantId] ?? ['quantity'=>0,'available'=>0], 'delivery' => $this->delivery($pincode, [$variantId])];
    }

    private function stock(array $variantIds, ?string $pincode): array
    {
        if (!$variantIds || !DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_warehouse_inventory')) return [];
        $q = DB::connection('priyasa')->table('priyasa_warehouse_inventory as wi')->join('priyasa_warehouses as w','w.id','=','wi.warehouse_id')->whereIn('wi.variant_id',$variantIds)->where('w.status','active')->where('w.supports_fulfillment',true);
        if ($pincode && DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_warehouse_serviceability')) {
            $q->leftJoin('priyasa_warehouse_serviceability as ws',function($j) use ($pincode){$j->on('ws.warehouse_id','=','w.id')->where('ws.pincode','=',$pincode);});
            $q->where(function($x){$x->whereNull('ws.id')->orWhere('ws.serviceable',true);});
        }
        $rows = $q->select('wi.variant_id','wi.quantity','wi.reserved_quantity','wi.damaged_quantity')->get();
        $out=[];
        foreach($rows as $r){$a=max(0,(int)$r->quantity-(int)$r->reserved_quantity-(int)$r->damaged_quantity); $k=(string)$r->variant_id; $out[$k]=['quantity'=>($out[$k]['quantity']??0)+(int)$r->quantity,'available'=>($out[$k]['available']??0)+$a];}
        return $out;
    }

    private function delivery(?string $pincode, array $variantIds): array
    {
        if (!$pincode) return ['pincode'=>null,'serviceable'=>null,'eta'=>null,'message'=>'Enter pincode to check delivery.'];
        if (!DB::connection('priyasa')->getSchemaBuilder()->hasTable('priyasa_warehouse_serviceability')) return ['pincode'=>$pincode,'serviceable'=>null,'eta'=>null];
        $ok = DB::connection('priyasa')->table('priyasa_warehouse_serviceability')->where('pincode',$pincode)->where('serviceable',true)->exists();
        return ['pincode'=>$pincode,'serviceable'=>$ok,'eta'=>null,'message'=>$ok?'Delivery serviceability is available; ETA is provider-dependent.':'Delivery is not currently serviceable.'];
    }
}
