<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Console;

use Illuminate\Console\Command;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\OrderService;

final class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'priyasa:release-reservations';
    protected $description = 'Cancel unpaid stale orders and release their reserved inventory';

    public function handle(OrderService $orders): int
    {
        $count = 0;
        Order::query()
            ->where('status', 'pending_payment')
            ->where('created_at', '<', now()->subMinutes((int) config('priyasacore.reservation_minutes', 15)))
            ->chunkById(100, function ($items) use ($orders, &$count): void {
                foreach ($items as $order) {
                    try {
                        $orders->transition($order, 'cancelled', 'system', null, 'Payment window expired');
                        $count++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->info("Cancelled {$count} expired order(s).");
        return self::SUCCESS;
    }
}
