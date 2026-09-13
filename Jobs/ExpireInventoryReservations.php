<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Models\InventoryReservation;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\InventoryReservationService;
use Modules\PriyasaCore\Services\OrderService;

final class ExpireInventoryReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(InventoryReservationService $reservations, OrderService $orders): void
    {
        $expired = InventoryReservation::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(500)
            ->get();

        $orderIds = [];
        foreach ($expired as $reservation) {
            $orderIds[(int) $reservation->order_id] = true;
            $reservations->release($reservation, 'expired');
        }

        foreach (array_keys($orderIds) as $orderId) {
            if (!$orderId) continue;
            $order = Order::query()->find($orderId);
            if (!$order || $order->status !== 'pending_payment') continue;

            $hasActive = InventoryReservation::query()
                ->where('order_id', $order->id)
                ->where('status', 'active')
                ->exists();

            if (!$hasActive) {
                $orders->transition($order, 'cancelled', 'system', null, 'Checkout inventory reservation expired.');
            }
        }
    }
}
