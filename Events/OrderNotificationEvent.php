<?php
namespace Modules\PriyasaCore\Events;
use Illuminate\Foundation\Events\Dispatchable;

final class OrderNotificationEvent
{
    use Dispatchable;
    public function __construct(public object $order, public string $event, public array $data = []) {}
}
