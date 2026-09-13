<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PriyasaCore\Contracts\PaymentGateway;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\Payment;
use Modules\PriyasaCore\Models\PaymentTransaction;
use RuntimeException;

final class PaymentService
{
    public function create(Order $order, PaymentGateway $gateway): Payment
    {
        return DB::connection('priyasa')->transaction(function () use ($order, $gateway) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, ['pending_payment','payment_failed'], true)) throw new RuntimeException('Payment cannot be started for this order.');
            $existing = $order->payment()->lockForUpdate()->first();
            if ($existing && $existing->status === 'created' && $existing->provider_order_id) return $existing;
            $remote = $gateway->createOrder($order);
            return Payment::updateOrCreate(['order_id'=>$order->id], [
                'provider'=>(string) config('priyasacore.payment_provider','razorpay'),
                'method'=>(string) ($order->payment_method ?: 'razorpay'),
                'provider_order_id'=>$remote['id'] ?? null,
                'status'=>'created','amount'=>$order->grand_total,'currency'=>$order->currency,'payload'=>$remote,
            ]);
        });
    }

    public function capture(Order $order, string $providerPaymentId, PaymentGateway $gateway, array $payload=[]): Payment
    {
        return DB::connection('priyasa')->transaction(function () use ($order, $providerPaymentId, $gateway, $payload) {
            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            if ($payment->status === 'captured') return $payment;
            if (! $gateway->verifyPayment($order, $providerPaymentId, $payload)) throw new RuntimeException('Payment verification failed.');
            $payment->update(['provider_payment_id'=>$providerPaymentId,'status'=>'captured','captured_at'=>now(),'payload'=>array_merge($payment->payload??[],$payload)]);
            PaymentTransaction::firstOrCreate(
                ['provider'=>$payment->provider,'provider_reference'=>$providerPaymentId,'type'=>'capture'],
                ['order_id'=>$order->id,'payment_id'=>$payment->id,'amount_paise'=>(int) round((float)$payment->amount*100),'status'=>'success','payload'=>$payload]
            );
            app(OrderService::class)->transition($order,'confirmed','payment',null,'Payment verified and captured');
            $customer = $order->customer()->lockForUpdate()->first();
            if ($customer) {
                try { app(PromotionService::class)->redeemForOrder($order, $customer); } catch (\Throwable $e) { Log::error('Coupon redemption failed after payment confirmation', ['order_id'=>$order->id,'error'=>$e->getMessage()]); }
                $customer->increment('order_count');
                $customer->update(['last_order_at'=>now(),'lifetime_value'=>DB::connection('priyasa')->raw('lifetime_value + '.(float)$order->grand_total)]);
            }
            return $payment->fresh();
        });
    }

    public function refund(Order $order, int $amountPaise, PaymentGateway $gateway, ?string $reason=null): array
    {
        return app(RefundService::class)->refund($order, $amountPaise, $gateway, $reason)->toArray();
    }
}
