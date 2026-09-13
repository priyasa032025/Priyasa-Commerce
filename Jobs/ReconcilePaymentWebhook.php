<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Payment;
use Modules\PriyasaCore\Models\PaymentTransaction;
use Modules\PriyasaCore\Models\WebhookEvent;
use Modules\PriyasaCore\Services\OrderService;
use RuntimeException;

final class ReconcilePaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        DB::connection('priyasa')->transaction(function (): void {
            $event = WebhookEvent::query()->lockForUpdate()->findOrFail($this->eventId);
            if ($event->status === 'processed') return;

            if ($event->provider !== 'razorpay') {
                $event->update(['status' => 'processed', 'processed_at' => now()]);
                return;
            }

            $payload = (array) $event->payload;
            $entity = $payload['payload']['payment']['entity']
                ?? $payload['payload']['order']['entity']
                ?? [];
            $paymentId = (string) ($entity['id'] ?? '');
            $providerOrderId = (string) ($entity['order_id'] ?? '');

            $payment = Payment::query()
                ->where(function ($query) use ($paymentId, $providerOrderId): void {
                    if ($paymentId !== '') $query->where('provider_payment_id', $paymentId);
                    if ($providerOrderId !== '') {
                        $paymentId !== '' ? $query->orWhere('provider_order_id', $providerOrderId) : $query->where('provider_order_id', $providerOrderId);
                    }
                })
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                $event->update(['status' => 'processed', 'processed_at' => now()]);
                return;
            }

            if (in_array($event->event_type, ['payment.captured', 'order.paid'], true)) {
                $amount = (int) ($entity['amount'] ?? 0);
                $expected = (int) round((float) $payment->amount * 100);
                if ($amount !== $expected) throw new RuntimeException('Webhook payment amount mismatch.');

                $payment->update([
                    'provider_payment_id' => $paymentId !== '' ? $paymentId : $payment->provider_payment_id,
                    'status' => 'captured',
                    'captured_at' => $payment->captured_at ?? now(),
                    'payload' => array_merge((array) $payment->payload, $payload),
                ]);

                $key = 'webhook:' . $event->provider . ':' . $event->event_id;
                PaymentTransaction::firstOrCreate(
                    ['idempotency_key' => $key],
                    [
                        'order_id' => $payment->order_id,
                        'payment_id' => $payment->id,
                        'provider' => $payment->provider,
                        'type' => 'capture',
                        'provider_reference' => $paymentId !== '' ? $paymentId : null,
                        'amount_paise' => $amount,
                        'status' => 'success',
                        'payload' => $payload,
                    ]
                );

                app(OrderService::class)->transition($payment->order, 'confirmed', 'gateway', null, 'Payment webhook reconciled.');
            } elseif ($event->event_type === 'payment.failed') {
                $payment->update([
                    'status' => 'failed',
                    'provider_payment_id' => $paymentId !== '' ? $paymentId : $payment->provider_payment_id,
                    'payload' => array_merge((array) $payment->payload, $payload),
                ]);

                app(OrderService::class)->transition($payment->order, 'payment_failed', 'gateway', null, 'Payment failure webhook reconciled.');
            }

            $event->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        });
    }
}
