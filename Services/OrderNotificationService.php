<?php
namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\NotificationDevice;
use Modules\PriyasaCore\Models\NotificationInbox;
use Modules\PriyasaCore\Jobs\SendFcmNotification;
use Modules\PriyasaCore\Jobs\SendWhatsAppOrderNotification;
use Modules\PriyasaCore\Jobs\SendEmailOrderNotification;

final class OrderNotificationService
{
    private const EVENTS = [
        'created' => ['title' => 'Order placed', 'body' => 'Your order has been placed successfully.', 'type' => 'order.created'],
        'confirmed' => ['title' => 'Order confirmed', 'body' => 'Your order has been confirmed.', 'type' => 'order.confirmed'],
        'payment_success' => ['title' => 'Payment successful', 'body' => 'Your payment was received successfully.', 'type' => 'payment.success'],
        'payment_failed' => ['title' => 'Payment failed', 'body' => 'We could not confirm your payment. Please try again.', 'type' => 'payment.failed'],
        'packed' => ['title' => 'Order packed', 'body' => 'Your order has been packed and is ready for dispatch.', 'type' => 'order.packed'],
        'shipped' => ['title' => 'Order shipped', 'body' => 'Your order is on its way.', 'type' => 'order.shipped'],
        'out_for_delivery' => ['title' => 'Out for delivery', 'body' => 'Your order is out for delivery.', 'type' => 'order.out_for_delivery'],
        'delivered' => ['title' => 'Order delivered', 'body' => 'Your order has been delivered.', 'type' => 'order.delivered'],
        'cancelled' => ['title' => 'Order cancelled', 'body' => 'Your order has been cancelled.', 'type' => 'order.cancelled'],
        'return_requested' => ['title' => 'Return requested', 'body' => 'Your return request has been received.', 'type' => 'return.requested'],
        'return_approved' => ['title' => 'Return approved', 'body' => 'Your return request has been approved.', 'type' => 'return.approved'],
        'return_rejected' => ['title' => 'Return update', 'body' => 'Your return request was not approved.', 'type' => 'return.rejected'],
        'refund_initiated' => ['title' => 'Refund initiated', 'body' => 'Your refund has been initiated.', 'type' => 'refund.initiated'],
        'refund_completed' => ['title' => 'Refund completed', 'body' => 'Your refund has been completed.', 'type' => 'refund.completed'],
    ];

    public function dispatch(object $order, string $event, array $extra = []): ?NotificationInbox
    {
        $definition = self::EVENTS[$event] ?? null;
        if (!$definition) return null;
        $userId = $order->user_id ?? null;
        $customerId = $order->customer_id ?? null;
        if (!$userId && !$customerId) return null;
        $orderId = (string)($order->id ?? 'unknown');
        $orderNumber = (string)($order->order_number ?? $orderId);
        $data = array_merge([
            'entity' => 'order',
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'event' => $event,
        ], $extra);
        $deepLink = app(DeepLinkService::class)->order($orderId);
        $dedupe = 'order:' . $orderId . ':' . $event;

        $inbox = DB::connection('priyasa')->transaction(function () use ($userId, $customerId, $definition, $data, $deepLink, $dedupe) {
            return NotificationInbox::firstOrCreate(['dedupe_key' => $dedupe], [
                'user_id' => $userId,
                'customer_id' => $customerId,
                'type' => $definition['type'],
                'title' => $definition['title'],
                'body' => $definition['body'],
                'data' => $data,
                'deep_link' => $deepLink['app_url'] ?? null,
            ]);
        });

        if ($inbox->wasRecentlyCreated) {
            $preferences = app(NotificationPreferenceService::class);
            if ($preferences->enabled($userId, $customerId, $definition['type'], 'fcm')) {
                $devices = NotificationDevice::query()->where('enabled', true)
                ->where(function ($q) use ($userId, $customerId) {
                    if ($userId) $q->where('user_id', $userId);
                    if ($customerId) $q->orWhere('customer_id', $customerId);
                })->get(['id']);
                foreach ($devices as $device) {
                    SendFcmNotification::dispatch((int)$device->id, $definition['title'], $definition['body'], array_merge($data, ['deep_link' => $deepLink['app_url'] ?? null]), (int)$inbox->id)
                        ->onQueue(config('p29_order_notifications.queue', 'notifications'));
                }
            }
            $phone = $order->phone ?? $order->customer_phone ?? data_get($order, 'customer.phone');
            if ($phone && $preferences->enabled($userId, $customerId, $definition['type'], 'whatsapp')) {
                SendWhatsAppOrderNotification::dispatch((string)$phone, $definition['title'].': '.$definition['body'], $orderId, $event)
                    ->onQueue(config('p29_order_notifications.queue', 'notifications'));
            }
            $email = $order->email ?? $order->customer_email ?? data_get($order, 'customer.email');
            if ($email && $preferences->enabled($userId, $customerId, $definition['type'], 'email')) {
                SendEmailOrderNotification::dispatch((string)$email, $definition['title'], $definition['body'], $orderId, $event)
                    ->onQueue(config('p29_order_notifications.queue', 'notifications'));
            }
        }
        return $inbox;
    }

    public static function supportedEvents(): array { return array_keys(self::EVENTS); }
}
