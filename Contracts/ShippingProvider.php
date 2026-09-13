<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Contracts;

use Modules\PriyasaCore\Models\Order;

interface ShippingProvider
{
    public function serviceability(string $pincode, string $paymentMode = 'prepaid'): array;
    public function createShipment(Order $order): array;
    public function cancelShipment(string $shipmentReference): array;
    public function track(string $trackingNumber): array;
}
