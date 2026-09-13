<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\PriyasaCore\Models\Order;

final class OrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $from,
        public string $to,
    ) {}
}
