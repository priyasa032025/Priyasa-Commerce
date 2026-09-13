<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PriyasaCore\Models\Cart;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\ProductVariant;
use Modules\PriyasaCore\Models\Order;
use RuntimeException;

final class CheckoutService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly InventoryService $inventory,
        private readonly OrderService $orders,
        private readonly PromotionService $promotions,
        private readonly InventoryReservationService $reservations,
    ) {}

    public function quote(Customer $customer, ?string $couponCode = null): array
    {
        $cart = Cart::query()
            ->where('customer_id', $customer->id)
            ->with(['items.variant.product', 'items.variant.inventory'])
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw new RuntimeException('Your cart is empty.');
        }

        $items = [];
        foreach ($cart->items as $item) {
            $variant = $item->variant;
            if (!$variant || !$variant->is_active || !$variant->product || $variant->product->status !== 'published') {
                throw new RuntimeException('One or more products in your cart are no longer available.');
            }

            $quantity = (int) $item->quantity;
            if ($quantity < 1 || $this->inventory->available($variant) < $quantity) {
                throw new RuntimeException("Insufficient stock for SKU {$variant->sku}.");
            }

            $items[] = ['variant' => $variant, 'quantity' => $quantity];
        }

        return $this->pricing->quote($items, $couponCode, $customer);
    }

    public function place(
        Customer $customer,
        int $shippingAddressId,
        string $paymentMethod,
        ?string $couponCode = null,
        string $source = 'website',
        ?string $idempotencyKey = null,
        float $shippingCharge = 0.0
    ): Order {
        return DB::connection('priyasa')->transaction(function () use ($customer, $shippingAddressId, $paymentMethod, $couponCode, $source, $idempotencyKey, $shippingCharge): Order {
            if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
                $existing = Order::query()->where('checkout_idempotency_key', $idempotencyKey)->first();
                if ($existing && (int) $existing->customer_id === (int) $customer->id) {
                    return $existing->fresh()->load('items');
                }
                if ($existing) {
                    throw new RuntimeException('Idempotency key is already associated with another customer.');
                }
            }
            $address = $customer->addresses()->whereKey($shippingAddressId)->first();
            if (!$address) {
                throw new RuntimeException('Shipping address does not belong to this customer.');
            }

            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->with(['items.variant.product', 'items.variant.inventory'])
                ->lockForUpdate()
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                throw new RuntimeException('Your cart is empty.');
            }

            $items = [];
            $variantMap = [];
            foreach ($cart->items as $item) {
                $variant = ProductVariant::query()
                    ->with('product')
                    ->lockForUpdate()
                    ->find($item->variant_id);

                if (!$variant || !$variant->is_active || !$variant->product || $variant->product->status !== 'published') {
                    throw new RuntimeException('One or more products in your cart are no longer available.');
                }

                $quantity = (int) $item->quantity;
                if ($quantity < 1 || $this->inventory->available($variant) < $quantity) {
                    throw new RuntimeException("Insufficient stock for SKU {$variant->sku}.");
                }

                $items[] = ['variant' => $variant, 'quantity' => $quantity];
                $variantMap[$variant->id] = $variant;
            }

            $quote = $this->pricing->quote($items, $couponCode, $customer);
            if (!is_finite($shippingCharge) || $shippingCharge < 0) {
                throw new RuntimeException('Invalid shipping charge.');
            }
            $quote['shipping_total'] = round($shippingCharge, 2);
            $quote['grand_total'] = round(
                (float) ($quote['subtotal'] ?? 0)
                - (float) ($quote['discount_total'] ?? 0)
                + (float) ($quote['tax_total'] ?? 0)
                + (float) $quote['shipping_total'],
                2
            );

            // Create the order inside the same outer transaction, then reserve
            // stock against the final order number. If reservation or any later
            // operation fails, the complete transaction is rolled back.
            $order = $this->orders->create([
                'shipping_address_id' => $address->id,
                'payment_method' => strtolower($paymentMethod),
                'source' => $source,
                'quote' => $quote,
                'variants' => $variantMap,
                'metadata' => [
                    'shipping_address_id' => $address->id,
                ],
                'checkout_idempotency_key' => $idempotencyKey,
            ], $customer);
            if (Schema::connection('priyasa')->hasColumn($order->getTable(), 'checkout_quote_hash')) {
                $order->forceFill(['checkout_quote_hash' => hash('sha256', json_encode([
                    'subtotal' => $quote['subtotal'] ?? 0,
                    'discount_total' => $quote['discount_total'] ?? 0,
                    'tax_total' => $quote['tax_total'] ?? 0,
                    'shipping_total' => $quote['shipping_total'] ?? 0,
                    'grand_total' => $quote['grand_total'] ?? 0,
                    'coupon' => $quote['coupon']['code'] ?? null,
                ], JSON_UNESCAPED_SLASHES))])->save();
            }

            if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
                $order->forceFill(['checkout_idempotency_key' => substr(trim($idempotencyKey), 0, 191)]);
                $order->save();
            }

            foreach ($items as $item) {
                $this->reservations->reserve(
                    $item['variant'],
                    (int) $item['quantity'],
                    (string) $order->order_number . ':' . (string) $item['variant']->id,
                    (int) $order->id
                );
            }

            if ($couponCode) {
                $this->promotions->reserveForOrder($order, $customer);
            }

            // COD is confirmed immediately; online payments remain pending until gateway verification.
            if (strtolower($paymentMethod) === 'cod') {
                $this->orders->confirmCod($order, 'system', null);
            }

            // The cart is cleared only after the order and reservations succeed.
            $cart->items()->delete();
            $cart->touch();

            return $order->fresh()->load('items');
        });
    }
}
