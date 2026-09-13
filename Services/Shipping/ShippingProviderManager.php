<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services\Shipping;
use RuntimeException;
final class ShippingProviderManager {
    public function driver(?string $name=null): ShippingProviderInterface { $name=$name ?: (string)config('p34_shipping.provider','shiprocket'); return match($name){'shiprocket'=>app(ShiprocketOrchestratorAdapter::class),default=>throw new RuntimeException('Unsupported shipping provider: '.$name)}; }
}
