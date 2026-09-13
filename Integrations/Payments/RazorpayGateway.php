<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Integrations\Payments;

use Illuminate\Support\Facades\Http;
use Modules\PriyasaCore\Contracts\PaymentGateway;
use Modules\PriyasaCore\Models\Order;
use RuntimeException;

final class RazorpayGateway implements PaymentGateway
{
    private function client()
    {
        $key = (string) config('priyasacore.razorpay.key_id');
        $secret = (string) config('priyasacore.razorpay.key_secret');
        if ($key === '' || $secret === '') throw new RuntimeException('Razorpay credentials are not configured.');
        return Http::withBasicAuth($key, $secret)->acceptJson()->timeout(15)->retry(2, 250);
    }

    public function createOrder(Order $order): array
    {
        $response = $this->client()->post('https://api.razorpay.com/v1/orders', [
            'amount' => (int) round((float) $order->grand_total * 100),
            'currency' => $order->currency,
            'receipt' => $order->order_number,
            'notes' => ['internal_order_id' => (string) $order->id],
        ]);
        $response->throw();
        return (array) $response->json();
    }

    public function verifyPayment(Order $order, string $providerPaymentId, array $payload): bool
    {
        $providerOrderId = (string) ($payload['razorpay_order_id'] ?? $payload['order_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature'] ?? $payload['signature'] ?? '');
        $expectedOrderId = (string) $order->payment?->provider_order_id;
        if ($providerOrderId === '' || $signature === '' || $expectedOrderId === '' || !hash_equals($expectedOrderId, $providerOrderId)) return false;
        $expected = hash_hmac('sha256', $providerOrderId . '|' . $providerPaymentId, (string) config('priyasacore.razorpay.key_secret'));
        if (!hash_equals($expected, $signature)) return false;
        $remote = $this->fetchPayment($providerPaymentId);
        return (int) ($remote['amount'] ?? 0) === (int) round((float) $order->grand_total * 100)
            && (string) ($remote['currency'] ?? '') === (string) $order->currency
            && hash_equals($expectedOrderId, (string) ($remote['order_id'] ?? ''))
            && hash_equals($providerPaymentId, (string) ($remote['id'] ?? ''))
            && (string) ($remote['status'] ?? '') === 'captured';
    }

    public function fetchPayment(string $paymentId): array
    {
        if ($paymentId === '') throw new RuntimeException('Payment reference is required.');
        $r = $this->client()->get('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId));
        $r->throw();
        return (array) $r->json();
    }

    public function refund(string $paymentReference, int $amountPaise, ?string $reason = null): array
    {
        if ($paymentReference === '' || $amountPaise < 1) throw new RuntimeException('Invalid refund request.');
        $r = $this->client()->post('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentReference) . '/refunds', [
            'amount' => $amountPaise,
            'notes' => ['reason' => $reason],
        ]);
        $r->throw();
        return (array) $r->json();
    }

    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) config('priyasacore.razorpay.webhook_secret');
        return $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
