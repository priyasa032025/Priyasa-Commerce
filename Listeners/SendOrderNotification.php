<?php
namespace Modules\PriyasaCore\Listeners;

use Modules\PriyasaCore\Events\OrderNotificationEvent;
use Modules\PriyasaCore\Services\OrderNotificationService;

final class SendOrderNotification
{
    public function handle(OrderNotificationEvent $event): void
    {
        app(OrderNotificationService::class)->dispatch($event->order, $event->event, $event->data);
    }
}
