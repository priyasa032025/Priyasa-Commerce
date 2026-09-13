<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Contracts;

use Modules\PriyasaCore\Models\Order;

interface PaymentGateway
{
    public function createOrder(Order $order): array;
    public function verifyPayment(Order $order, string $providerPaymentId, array $payload): bool;
    public function refund(string $paymentReference, int $amountPaise, ?string $reason = null): array;
}
