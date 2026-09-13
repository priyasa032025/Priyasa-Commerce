<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\PaymentTransaction;
use RuntimeException;

final class CheckoutReliabilityService
{
    public function assertOrderPayable(Order $order): void
    {
        $order->refresh();
        if (!in_array((string) $order->status, ['pending_payment', 'payment_failed'], true)) {
            throw new RuntimeException('Order is no longer payable.');
        }
        if ((float) $order->grand_total < 0) throw new RuntimeException('Invalid order total.');
    }

    public function transactionKey(string $provider, string $reference, string $type): string
    {
        return hash('sha256', $provider . '|' . $reference . '|' . $type);
    }

    public function hasTransaction(string $key): bool
    {
        return PaymentTransaction::where('idempotency_key', $key)->exists();
    }

    public function checkoutKeyFor(string $key): string
    {
        return substr(trim($key), 0, 191);
    }

    public function existingOrder(string $key, int $customerId): ?Order
    {
        if (trim($key) === '') return null;
        return Order::query()
            ->where('checkout_idempotency_key', $this->checkoutKeyFor($key))
            ->where('customer_id', $customerId)
            ->first();
    }
}
