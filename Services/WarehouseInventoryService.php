<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WarehouseInventoryService
{
    public function adjust(int $warehouseId, int $variantId, int $delta, string $reason='manual', ?string $referenceType=null, ?string $referenceId=null): array
    {
        return DB::connection('priyasa')->transaction(function () use ($warehouseId,$variantId,$delta,$reason,$referenceType,$referenceId) {
            $row=DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('warehouse_id',$warehouseId)->where('variant_id',$variantId)->lockForUpdate()->first();
            if(!$row) throw ValidationException::withMessages(['inventory'=>'Warehouse inventory record not found.']);
            $available=(int)$row->quantity-(int)$row->reserved_quantity;
            if($delta<0 && $available < abs($delta)) throw ValidationException::withMessages(['inventory'=>'Insufficient available stock.']);
            DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('id',$row->id)->update(['quantity'=>(int)$row->quantity+$delta,'updated_at'=>now()]);
            DB::connection('priyasa')->table('priyasa_inventory_ledger')->insert(['warehouse_id'=>$warehouseId,'variant_id'=>$variantId,'quantity_delta'=>$delta,'quantity_after'=>(int)$row->quantity+$delta,'reason'=>$reason,'reference_type'=>$referenceType,'reference_id'=>$referenceId,'created_at'=>now(),'updated_at'=>now()]);
            return ['warehouse_id'=>$warehouseId,'variant_id'=>$variantId,'quantity'=>(int)$row->quantity+$delta,'reserved_quantity'=>(int)$row->reserved_quantity,'available_quantity'=>$available+$delta];
        });
    }

    public function reserve(int $warehouseId,int $variantId,int $quantity): array
    {
        if($quantity<1) throw ValidationException::withMessages(['quantity'=>'Quantity must be at least 1.']);
        return DB::connection('priyasa')->transaction(function() use($warehouseId,$variantId,$quantity){
            $row=DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('warehouse_id',$warehouseId)->where('variant_id',$variantId)->lockForUpdate()->first();
            if(!$row || ((int)$row->quantity-(int)$row->reserved_quantity)<$quantity) throw ValidationException::withMessages(['inventory'=>'Insufficient available stock.']);
            DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('id',$row->id)->update(['reserved_quantity'=>(int)$row->reserved_quantity+$quantity,'updated_at'=>now()]);
            DB::connection('priyasa')->table('priyasa_inventory_ledger')->insert(['warehouse_id'=>$warehouseId,'variant_id'=>$variantId,'quantity_delta'=>0,'reserved_delta'=>$quantity,'quantity_after'=>(int)$row->quantity,'reason'=>'reservation','created_at'=>now(),'updated_at'=>now()]);
            return ['warehouse_id'=>$warehouseId,'variant_id'=>$variantId,'quantity'=>(int)$row->quantity,'reserved_quantity'=>(int)$row->reserved_quantity+$quantity,'available_quantity'=>(int)$row->quantity-(int)$row->reserved_quantity-$quantity];
        });
    }

    public function release(int $warehouseId,int $variantId,int $quantity): array
    {
        return DB::connection('priyasa')->transaction(function() use($warehouseId,$variantId,$quantity){
            $row=DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('warehouse_id',$warehouseId)->where('variant_id',$variantId)->lockForUpdate()->first();
            if(!$row) throw ValidationException::withMessages(['inventory'=>'Warehouse inventory record not found.']);
            $release=min($quantity,(int)$row->reserved_quantity);
            DB::connection('priyasa')->table('priyasa_warehouse_inventory')->where('id',$row->id)->update(['reserved_quantity'=>(int)$row->reserved_quantity-$release,'updated_at'=>now()]);
            DB::connection('priyasa')->table('priyasa_inventory_ledger')->insert(['warehouse_id'=>$warehouseId,'variant_id'=>$variantId,'quantity_delta'=>0,'reserved_delta'=>-$release,'quantity_after'=>(int)$row->quantity,'reason'=>'reservation_release','created_at'=>now(),'updated_at'=>now()]);
            return ['warehouse_id'=>$warehouseId,'variant_id'=>$variantId,'quantity'=>(int)$row->quantity,'reserved_quantity'=>(int)$row->reserved_quantity-$release,'available_quantity'=>(int)$row->quantity-(int)$row->reserved_quantity+$release];
        });
    }
}
