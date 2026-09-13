<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Inventory;
use Modules\PriyasaCore\Models\InventoryMovement;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\ProductVariant;
use RuntimeException;

final class InventoryService
{
    public function available(ProductVariant $variant): int
    {
        $inventory = $variant->inventory;
        return $inventory ? max(0, (int) $inventory->quantity - (int) $inventory->reserved_quantity) : 0;
    }

    public function reserve(ProductVariant $variant, int $quantity, string $reference): void
    {
        if ($quantity < 1) {
            throw new RuntimeException('Quantity must be positive.');
        }

        DB::connection('priyasa')->transaction(function () use ($variant, $quantity, $reference): void {
            $inventory = Inventory::query()->lockForUpdate()->firstOrCreate(
                ['variant_id' => $variant->id],
                ['quantity' => 0, 'reserved_quantity' => 0, 'low_stock_threshold' => 3]
            );
            $available = (int) $inventory->quantity - (int) $inventory->reserved_quantity;
            if ($available < $quantity) {
                throw new RuntimeException("Insufficient stock for SKU {$variant->sku}.");
            }

            $inventory->increment('reserved_quantity', $quantity);
            $inventory->refresh();
            InventoryMovement::create([
                'variant_id' => $variant->id,
                'type' => 'reservation',
                'quantity' => $quantity,
                'balance_after' => $inventory->quantity,
                'reference_type' => 'order',
                'reference_id' => $reference,
                'reason' => 'Checkout stock reservation',
            ]);
        });
    }

    public function release(ProductVariant $variant, int $quantity, string $reference): void
    {
        if ($quantity < 1) return;

        DB::connection('priyasa')->transaction(function () use ($variant, $quantity, $reference): void {
            $inventory = Inventory::query()->lockForUpdate()->where('variant_id', $variant->id)->first();
            if (!$inventory) return;
            $released = min($quantity, (int) $inventory->reserved_quantity);
            if ($released < 1) return;
            $inventory->decrement('reserved_quantity', $released);
            $inventory->refresh();
            InventoryMovement::create([
                'variant_id' => $variant->id,
                'type' => 'reservation_release',
                'quantity' => -$released,
                'balance_after' => $inventory->quantity,
                'reference_type' => 'order',
                'reference_id' => $reference,
                'reason' => 'Reservation released',
            ]);
        });
    }

    public function setQuantity(ProductVariant $variant, int $quantity, int $threshold = 3, string $reason = 'Admin stock update'): Inventory
    {
        if ($quantity < 0) throw new RuntimeException('Quantity cannot be negative.');
        if ($threshold < 0) throw new RuntimeException('Low-stock threshold cannot be negative.');
        return DB::connection('priyasa')->transaction(function () use ($variant, $quantity, $threshold, $reason): Inventory {
            $inventory = Inventory::query()->lockForUpdate()->firstOrCreate(
                ['variant_id' => $variant->id],
                ['quantity' => 0, 'reserved_quantity' => 0, 'low_stock_threshold' => 3]
            );
            if ($quantity < (int)$inventory->reserved_quantity) throw new RuntimeException('Quantity cannot be below reserved quantity.');
            $old=(int)$inventory->quantity; $delta=$quantity-$old;
            $inventory->quantity=$quantity; $inventory->low_stock_threshold=$threshold; $inventory->save();
            if ($delta !== 0) InventoryMovement::create([
                'variant_id'=>$variant->id,'type'=>'stock_set','quantity'=>$delta,'balance_after'=>$quantity,
                'reference_type'=>'admin','reference_id'=>null,'reason'=>$reason,
            ]);
            return $inventory->fresh();
        });
    }

    public function adjust(ProductVariant $variant, int $delta, string $reason = 'Admin adjustment'): Inventory
    {
        return DB::connection('priyasa')->transaction(function () use ($variant, $delta, $reason): Inventory {
            $inventory = Inventory::query()->lockForUpdate()->firstOrCreate(
                ['variant_id' => $variant->id],
                ['quantity' => 0, 'reserved_quantity' => 0, 'low_stock_threshold' => 3]
            );
            $new = (int) $inventory->quantity + $delta;
            if ($new < (int) $inventory->reserved_quantity) {
                throw new RuntimeException('Inventory cannot fall below reserved quantity.');
            }
            $inventory->quantity = $new;
            $inventory->save();
            InventoryMovement::create([
                'variant_id' => $variant->id,
                'type' => 'adjustment',
                'quantity' => $delta,
                'balance_after' => $new,
                'reason' => $reason,
            ]);
            return $inventory->fresh();
        });
    }

    public function releaseOrder(Order $order): void
    {
        DB::connection('priyasa')->transaction(function () use ($order): void {
            $items = $order->items()->get();
            foreach ($items as $item) {
                $this->release(ProductVariant::findOrFail($item->variant_id), (int) $item->quantity, (string) $order->order_number);
            }
        });
    }

    public function commitOrder(Order $order): void
    {
        DB::connection('priyasa')->transaction(function () use ($order): void {
            $items = $order->items()->get();
            foreach ($items as $item) {
                $this->commitSale(ProductVariant::findOrFail($item->variant_id), (int) $item->quantity, (string) $order->order_number);
            }
        });
    }

    public function restockOrder(Order $order): void
    {
        DB::connection('priyasa')->transaction(function () use ($order): void {
            foreach ($order->items()->get() as $item) {
                $this->restockSale(ProductVariant::findOrFail($item->variant_id), (int) $item->quantity, (string) $order->order_number);
            }
        });
    }

    public function restockReturn(\Modules\PriyasaCore\Models\ReturnRequest $return): void
    {
        DB::connection('priyasa')->transaction(function () use ($return): void {
            foreach ($return->items()->get() as $returnItem) {
                $this->restockSale(ProductVariant::findOrFail($returnItem->orderItem->variant_id), (int) $returnItem->quantity, 'RETURN-' . $return->id);
            }
        });
    }

    private function restockSale(ProductVariant $variant, int $quantity, string $reference): void
    {
        if ($quantity < 1) return;
        DB::connection('priyasa')->transaction(function () use ($variant, $quantity, $reference): void {
            $inventory = Inventory::query()->lockForUpdate()->firstOrCreate(
                ['variant_id' => $variant->id],
                ['quantity' => 0, 'reserved_quantity' => 0, 'low_stock_threshold' => 3]
            );
            $inventory->increment('quantity', $quantity);
            $inventory->refresh();
            InventoryMovement::create([
                'variant_id' => $variant->id,
                'type' => 'return_restock',
                'quantity' => $quantity,
                'balance_after' => $inventory->quantity,
                'reference_type' => 'order',
                'reference_id' => $reference,
                'reason' => 'Cancelled/returned order restock',
            ]);
        });
    }

    private function commitSale(ProductVariant $variant, int $quantity, string $reference): void
    {
        if ($quantity < 1) return;
        DB::connection('priyasa')->transaction(function () use ($variant, $quantity, $reference): void {
            $inventory = Inventory::query()->lockForUpdate()->where('variant_id', $variant->id)->firstOrFail();
            if ((int) $inventory->reserved_quantity < $quantity || (int) $inventory->quantity < $quantity) {
                throw new RuntimeException('Reserved stock is inconsistent.');
            }
            $inventory->decrement('quantity', $quantity);
            $inventory->decrement('reserved_quantity', $quantity);
            $inventory->refresh();
            InventoryMovement::create([
                'variant_id' => $variant->id,
                'type' => 'sale',
                'quantity' => -$quantity,
                'balance_after' => $inventory->quantity,
                'reference_type' => 'order',
                'reference_id' => $reference,
                'reason' => 'Payment confirmed',
            ]);
        });
    }
}
