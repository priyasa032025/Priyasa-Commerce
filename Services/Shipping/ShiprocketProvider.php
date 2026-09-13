<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services\Shipping;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ShiprocketProvider implements ShippingProviderInterface
{
    private string $baseUrl;
    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('priyasacore.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external'), '/');
    }

    public function createShipment(array $payload): array
    {
        return $this->request('POST', '/orders/create/adhoc', $payload, true);
    }
    public function generateLabel(string $providerShipmentId): array
    {
        return $this->request('POST', '/courier/generate/label', ['shipment_id' => [$providerShipmentId]], true);
    }
    public function schedulePickup(string $providerShipmentId): array
    {
        return $this->request('POST', '/courier/generate/pickup', ['shipment_id' => [$providerShipmentId]], true);
    }
    public function track(string $providerShipmentId): array
    {
        return $this->request('GET', '/courier/track/shipment/'.rawurlencode($providerShipmentId), [], true);
    }
    public function cancel(string $providerShipmentId): array
    {
        return $this->request('POST', '/orders/cancel', ['ids' => [$providerShipmentId]], true);
    }

    private function request(string $method, string $path, array $payload, bool $authenticated): array
    {
        $token = (string) config('priyasacore.shiprocket.token', env('SHIPROCKET_TOKEN', ''));
        $http = Http::acceptJson()->timeout((int) config('priyasacore.shiprocket.timeout', 15));
        if ($authenticated && $token !== '') $http = $http->withToken($token);
        $response = $method === 'GET' ? $http->get($this->baseUrl.$path, $payload) : $http->post($this->baseUrl.$path, $payload);
        if ($response->failed()) throw new RuntimeException('Shipping provider request failed: HTTP '.$response->status());
        return (array) $response->json();
    }
}
