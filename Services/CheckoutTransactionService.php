<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use RuntimeException;

/**
 * Single server-authoritative checkout orchestration boundary.
 * The client never supplies totals; all monetary values are rebuilt here.
 */
final class CheckoutTransactionService
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CheckoutExperienceService $delivery,
    ) {}

    public function quote(Customer $customer, int $addressId, ?string $couponCode = null, bool $prepaid = true): array
    {
        $base = $this->checkout->quote($customer, $couponCode);
        $baseTotal = $this->baseTotal($base);
        $shipping = $this->delivery->delivery($customer, $addressId, $baseTotal);
        if (!$shipping['serviceable']) {
            throw new RuntimeException('Delivery is unavailable for this pincode.');
        }
        if (!$prepaid && !($shipping['cod']['eligible'] ?? false)) {
            throw new RuntimeException('Cash on Delivery is unavailable for this order.');
        }

        return $this->finalizeQuote($base, $shipping, $prepaid);
    }

    public function place(Customer $customer, int $addressId, string $paymentMethod, ?string $couponCode = null, string $source = 'website', ?string $idempotencyKey = null): Order
    {
        $paymentMethod = strtolower(trim($paymentMethod));
        if (!in_array($paymentMethod, ['razorpay', 'cod'], true)) {
            throw new RuntimeException('Unsupported payment method.');
        }

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $existing = app(CheckoutReliabilityService::class)->existingOrder($idempotencyKey, (int) $customer->id);
            if ($existing) return $existing->fresh()->load('items');
        }

        // Rebuild the quote immediately before order creation. This is deliberate:
        // prices, stock, promotion eligibility and delivery can change between quote and place.
        $quote = $this->quote($customer, $addressId, $couponCode, $paymentMethod !== 'cod');
        $shipping = (float) ($quote['shipping_total'] ?? 0);
        $order = $this->checkout->place(
            $customer,
            $addressId,
            $paymentMethod,
            $couponCode,
            $source,
            $idempotencyKey,
            $shipping
        );

        $expected = $this->money($quote['grand_total'] ?? 0);
        $actual = $this->money($order->grand_total);
        if (abs($expected - $actual) > 0.01) {
            throw new RuntimeException('Checkout total changed during order creation; please retry.');
        }

        return $order;
    }

    private function finalizeQuote(array $quote, array $shipping, bool $prepaid): array
    {
        $quote['shipping_total'] = $this->money($shipping['shipping_charge'] ?? 0);
        $quote['delivery'] = $shipping;
        $quote['payment'] = [
            'method_options' => [
                ['method' => 'razorpay', 'enabled' => true],
                ['method' => 'cod', 'enabled' => (bool) ($shipping['cod']['eligible'] ?? false)],
            ],
            'selected_prepaid' => $prepaid,
        ];
        $quote['grand_total'] = $this->money(
            (float) ($quote['subtotal'] ?? 0)
            - (float) ($quote['discount_total'] ?? 0)
            + (float) ($quote['tax_total'] ?? 0)
            + (float) $quote['shipping_total']
        );
        $quote['checkout_authority'] = 'server';
        return $quote;
    }

    private function baseTotal(array $quote): float
    {
        // PricingService may expose grand_total or total. Prefer component arithmetic
        // when available so an old shipping_total cannot be silently double charged.
        if (array_key_exists('subtotal', $quote)) {
            return $this->money(
                (float) $quote['subtotal']
                - (float) ($quote['discount_total'] ?? 0)
                + (float) ($quote['tax_total'] ?? 0)
            );
        }
        return $this->money((float) ($quote['total'] ?? $quote['grand_total'] ?? 0));
    }

    private function money(float $value): float
    {
        if (!is_finite($value) || $value < 0) throw new RuntimeException('Invalid checkout monetary value.');
        return round($value, 2);
    }
}
