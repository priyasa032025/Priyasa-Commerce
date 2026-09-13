<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\OrderStatusHistory;
use Modules\PriyasaCore\Models\OutboxEvent;
use Modules\PriyasaCore\Models\InventoryReservation;
use RuntimeException;

final class OrderService
{
    private const TRANSITIONS = [
        'pending_payment' => ['payment_failed', 'confirmed', 'cancelled'],
        'payment_failed' => ['pending_payment', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['packed', 'cancelled'],
        'packed' => ['shipped', 'cancelled'],
        'shipped' => ['in_transit'],
        'in_transit' => ['out_for_delivery', 'delivered'],
        'out_for_delivery' => ['delivered'],
        'delivered' => ['return_requested'],
        'return_requested' => ['returned', 'delivered'],
        'returned' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function create(array $data, Customer $customer): Order
    {
        return DB::connection('priyasa')->transaction(function () use ($data, $customer): Order {
            $quote = (array) ($data['quote'] ?? []);
            if (strtolower((string)($data['payment_method'] ?? '')) === 'cod') {
                $data['metadata']['cod'] = true;
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'shipping_address_id' => $data['shipping_address_id'] ?? null,
                'order_number' => $this->number(),
                'status' => 'pending_payment',
                'currency' => (string) ($quote['currency'] ?? config('priyasacore.currency', 'INR')),
                'subtotal' => (float) ($quote['subtotal'] ?? 0),
                'discount_total' => (float) ($quote['discount_total'] ?? 0),
                'tax_total' => (float) ($quote['tax_total'] ?? 0),
                'shipping_total' => (float) ($quote['shipping_total'] ?? 0),
                'grand_total' => (float) ($quote['grand_total'] ?? 0),
                'payment_status' => 'pending',
                'payment_method' => $data['payment_method'] ?? null,
                'source' => $data['source'] ?? 'website',
                'metadata' => array_merge((array) ($data['metadata'] ?? []), [
                    'coupon_code' => $quote['coupon']['code'] ?? null,
                ]),
            ]);

            foreach ((array) ($quote['lines'] ?? []) as $line) {
                $variant = $data['variants'][$line['variant_id']] ?? null;
                if (!$variant) throw new RuntimeException('Checkout variant snapshot is missing.');
                $order->items()->create([
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'product_name' => $variant->product->name,
                    'variant_label' => trim(implode(' / ', array_filter([$variant->size, $variant->color]))),
                    'quantity' => (int) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'tax_amount' => (float) ($line['tax_amount'] ?? 0),
                    'line_total' => (float) $line['line_total'],
                    'snapshot' => [
                        'product_id' => $variant->product_id,
                        'product_name' => $variant->product->name,
                        'sku' => $variant->sku,
                        'size' => $variant->size,
                        'color' => $variant->color,
                    ],
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending_payment',
                'actor_type' => 'system',
                'actor_id' => null,
                'note' => 'Order created',
            ]);
            $outbox = OutboxEvent::create([
                'event_type' => 'order.status.changed',
                'payload' => ['order_id' => $order->id, 'from' => null, 'to' => 'pending_payment'],
                'occurred_at' => now(),
            ]);
            event(new \Modules\PriyasaCore\Events\OrderStatusChanged($order->fresh(), null, 'pending_payment'));
            return $order->load('items');
        });
    }

    public function transition(Order $order, string $to, string $actorType = 'system', ?string $actorId = null, ?string $note = null): Order
    {
        return DB::connection('priyasa')->transaction(function () use ($order, $to, $actorType, $actorId, $note): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = (string) $order->status;
            if ($from === $to) return $order->fresh();
            if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw new RuntimeException("Invalid order transition: {$from} -> {$to}");
            }

            if ($to === 'confirmed') {
                if (!app(InventoryReservationService::class)->commitOrder($order)) {
                    app(InventoryService::class)->commitOrder($order);
                }
                $order->payment_status = 'paid';
                $order->placed_at ??= now();
            } elseif (in_array($to, ['payment_failed', 'cancelled'], true) && in_array($from, ['pending_payment', 'payment_failed'], true)) {
                if (!app(InventoryReservationService::class)->releaseOrder($order, $to === 'payment_failed' ? 'payment_failed' : 'cancelled')) {
                    app(InventoryService::class)->releaseOrder($order);
                }
                if ($to === 'payment_failed') $order->payment_status = 'failed';
                if ($to === 'cancelled') {
                    $order->payment_status = 'cancelled';
                    app(PromotionService::class)->releaseForOrder($order);
                }
            } elseif ($to === 'pending_payment' && $from === 'payment_failed') {
                app(InventoryReservationService::class)->ensureOrderReservations($order);
                $order->payment_status = 'pending';
            } elseif ($to === 'cancelled' && in_array($from, ['confirmed', 'processing', 'packed'], true)) {
                app(InventoryService::class)->restockOrder($order);
                if ($order->payment_status === 'paid') $order->payment_status = 'refund_pending';
            }

            $order->status = $to;
            $order->save();
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'note' => $note,
            ]);
            $outbox = OutboxEvent::create([
                'event_type' => 'order.status.changed',
                'payload' => ['order_id' => $order->id, 'from' => $from, 'to' => $to],
                'occurred_at' => now(),
            ]);
            event(new \Modules\PriyasaCore\Events\OrderStatusChanged($order->fresh(), $from, $to));
            return $order->fresh();
        });
    }

    public function confirmCod(Order $order, string $actorType = 'system', ?string $actorId = null): Order
    {
        return DB::connection('priyasa')->transaction(function () use ($order, $actorType, $actorId): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (strtolower((string)$order->payment_method) !== 'cod') {
                throw new RuntimeException('Only COD orders can be confirmed without online payment.');
            }
            if ($order->status === 'confirmed') return $order->fresh();
            if ($order->status !== 'pending_payment') throw new RuntimeException('COD order is not awaiting confirmation.');
            if (!app(InventoryReservationService::class)->commitOrder($order)) {
                app(InventoryService::class)->commitOrder($order);
            }
            $order->payment_status = 'pending';
            $order->placed_at ??= now();
            $order->status = 'confirmed';
            $order->save();
            OrderStatusHistory::create(['order_id'=>$order->id,'from_status'=>'pending_payment','to_status'=>'confirmed','actor_type'=>$actorType,'actor_id'=>$actorId,'note'=>'COD order confirmed']);
            OutboxEvent::create(['event_type'=>'order.status.changed','payload'=>['order_id'=>$order->id,'from'=>'pending_payment','to'=>'confirmed'],'occurred_at'=>now()]);
            event(new \Modules\PriyasaCore\Events\OrderStatusChanged($order->fresh(),'pending_payment','confirmed'));
            return $order->fresh();
        });
    }

    private function number(): string
    {
        do {
            $number = config('priyasacore.order_number_prefix', 'PRI') . '-' . now()->format('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        } while (Order::where('order_number', $number)->exists());
        return $number;
    }
}
