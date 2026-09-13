<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services\Shipping;

interface ShippingProviderInterface
{
    public function createShipment(array $payload): array;
    public function generateLabel(string $providerShipmentId): array;
    public function schedulePickup(string $providerShipmentId): array;
    public function track(string $providerShipmentId): array;
    public function cancel(string $providerShipmentId): array;
}
