<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Modules\PriyasaCore\Models\Customer;
use RuntimeException;

final class PricingService
{
    public function quote(array $items, ?string $couponCode = null, ?Customer $customer = null): array
    {
        $subtotal = 0.0;
        $mrpTotal = 0.0;
        $lines = [];
        $taxTotal = 0.0;

        foreach ($items as $item) {
            $variant = $item['variant'];
            $qty = max(1, (int) $item['quantity']);
            $unit = (float) ($variant->price ?? $variant->product->price ?? 0);
            $mrp = (float) ($variant->mrp ?? $variant->product->mrp ?? $unit);
            $line = round($unit * $qty, 2);
            $mrpLine = round($mrp * $qty, 2);
            $subtotal += $line;
            $mrpTotal += $mrpLine;
            $lines[] = [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'quantity' => $qty,
                'unit_price' => $unit,
                'mrp_unit_price' => $mrp,
                'discount' => round(max(0, $mrp - $unit) * $qty, 2),
                'line_total' => $line,
            ];
        }

        $promotion = app(PromotionService::class)->calculate($customer, $items, $subtotal, $couponCode);
        $discount = (float) ($promotion['discount'] ?? 0);
        $coupon = $promotion['coupon'] ?? null;

        // Tax is calculated per line so variant GST can override product/default GST.
        $taxableAfterCoupon = max(0, $subtotal - $discount);
        foreach ($lines as &$line) {
            $share = $subtotal > 0 ? ((float)$line['line_total'] / $subtotal) : 0;
            $taxable = round($taxableAfterCoupon * $share, 2);
            $variant = collect($items)->firstWhere('variant.id', $line['variant_id'])['variant'] ?? null;
            $rate = $variant ? (float)($variant->gst_rate ?? $variant->product->gst_rate ?? $variant->product->tax_rate ?? config('priyasacore.default_tax_rate',0)) : (float)config('priyasacore.default_tax_rate',0);
            $lineTax = round($taxable * ($rate / 100), 2);
            $line['gst_rate'] = $rate;
            $line['taxable_amount'] = $taxable;
            $line['tax_amount'] = $lineTax;
            $taxTotal += $lineTax;
        }
        unset($line);

        $shipping = 0.0;
        return [
            'currency' => config('priyasacore.currency', 'INR'),
            'lines' => $lines,
            'mrp_total' => round($mrpTotal, 2),
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($taxTotal, 2),
            'shipping_total' => $shipping,
            'grand_total' => round($subtotal - $discount + $taxTotal + $shipping, 2),
            'coupon' => $coupon,
            'promotions' => $promotion['promotions'] ?? [],
        ];
    }
}
