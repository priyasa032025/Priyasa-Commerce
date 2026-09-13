<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Product;
use Modules\PriyasaCore\Models\ProductVariant;
use RuntimeException;

final class BulkCatalogService
{
    public function prices(array $data, ?int $actorUserId = null): int
    {
        return DB::connection('priyasa')->transaction(function () use ($data, $actorUserId): int {
            $variants = ProductVariant::query()->with('product')->whereIn('id', $data['variant_ids'] ?? [])->lockForUpdate()->get();
            $products = Product::query()->whereIn('id', $data['product_ids'] ?? [])->lockForUpdate()->get();
            if ($variants->isEmpty() && $products->isEmpty()) throw new RuntimeException('No products or variants selected.');
            $fields = array_values(array_filter([$data['mrp'] ?? null, $data['price'] ?? null], fn($v)=>$v !== null));
            if (!$fields) throw new RuntimeException('Provide mrp and/or price operation.');
            $updated = 0;
            foreach ($variants as $variant) {
                foreach (['mrp','price'] as $field) {
                    if (!isset($data[$field])) continue;
                    $old = (float)($variant->{$field} ?? 0);
                    $new = $this->calculate($old, $data[$field]);
                    if ($new < 0) throw new RuntimeException("{$field} cannot be negative.");
                    $variant->{$field} = $new; $variant->save();
                    DB::connection('priyasa')->table('priyasa_price_change_logs')->insert(['product_id'=>$variant->product_id,'variant_id'=>$variant->id,'field'=>$field,'old_value'=>$old,'new_value'=>$new,'operation'=>$data[$field]['operation'] ?? 'set','operation_value'=>$data[$field]['value'] ?? null,'actor_user_id'=>$actorUserId,'source'=>'admin','reason'=>$data['reason'] ?? null,'created_at'=>now(),'updated_at'=>now()]);
                }
                $updated++;
            }
            foreach ($products as $product) {
                foreach (['mrp','price'] as $field) {
                    if (!isset($data[$field])) continue;
                    $old = (float)$product->{$field}; $new = $this->calculate($old, $data[$field]);
                    if ($new < 0) throw new RuntimeException("{$field} cannot be negative.");
                    $product->{$field}=$new; $product->save();
                    DB::connection('priyasa')->table('priyasa_price_change_logs')->insert(['product_id'=>$product->id,'variant_id'=>null,'field'=>$field,'old_value'=>$old,'new_value'=>$new,'operation'=>$data[$field]['operation'] ?? 'set','operation_value'=>$data[$field]['value'] ?? null,'actor_user_id'=>$actorUserId,'source'=>'admin','reason'=>$data['reason'] ?? null,'created_at'=>now(),'updated_at'=>now()]);
                    if (!empty($data['apply_to_variants'])) {
                        foreach ($product->variants()->lockForUpdate()->get() as $variant) {
                            $vold=(float)($variant->{$field} ?? 0); $vnew=$this->calculate($vold,$data[$field]);
                            $variant->{$field}=$vnew; $variant->save();
                            DB::connection('priyasa')->table('priyasa_price_change_logs')->insert(['product_id'=>$product->id,'variant_id'=>$variant->id,'field'=>$field,'old_value'=>$vold,'new_value'=>$vnew,'operation'=>$data[$field]['operation'] ?? 'set','operation_value'=>$data[$field]['value'] ?? null,'actor_user_id'=>$actorUserId,'source'=>'admin','reason'=>$data['reason'] ?? null,'created_at'=>now(),'updated_at'=>now()]);
                        }
                    }
                }
                $updated++;
            }
            return $updated;
        });
    }

    private function calculate(float $old, array $operation): float
    {
        $type = $operation['operation'] ?? 'set'; $value = (float)($operation['value'] ?? 0);
        return round(match ($type) {
            'set' => $value,
            'increase_amount' => $old + $value,
            'decrease_amount' => $old - $value,
            'increase_percentage' => $old + ($old * $value / 100),
            'decrease_percentage' => $old - ($old * $value / 100),
            default => throw new RuntimeException('Unsupported price operation.'),
        }, 2);
    }
}
