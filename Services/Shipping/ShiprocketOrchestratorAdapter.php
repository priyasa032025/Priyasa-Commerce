<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services\Shipping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ShiprocketOrchestratorAdapter implements ShippingProviderInterface
{
    private function client()
    {
        $token = Cache::remember('priyasa:shiprocket:p34:token', now()->addHours(8), function () {
            $r = Http::acceptJson()->timeout((int)config('p34_shipping.timeout',20))->post(rtrim((string)config('p34_shipping.shiprocket.base_url'),'/' ).'/auth/login', ['email'=>config('p34_shipping.shiprocket.email'),'password'=>config('p34_shipping.shiprocket.password')]);
            $r->throw(); $token=(string)$r->json('token'); if($token==='') throw new RuntimeException('Shiprocket authentication returned no token.'); return $token;
        });
        return Http::withToken($token)->acceptJson()->timeout((int)config('p34_shipping.timeout',20))->retry((int)config('p34_shipping.retry',2),300);
    }
    private function url(string $path): string { return rtrim((string)config('p34_shipping.shiprocket.base_url'),'/' ).$path; }
    public function createShipment(array $payload): array { $r=$this->client()->post($this->url('/orders/create/adhoc'),$payload); $r->throw(); return (array)$r->json(); }
    public function generateLabel(string $providerShipmentId): array { $r=$this->client()->post($this->url('/courier/generate/label'),['shipment_id'=>[(int)$providerShipmentId]]); $r->throw(); return (array)$r->json(); }
    public function schedulePickup(string $providerShipmentId): array { $r=$this->client()->post($this->url('/courier/generate/pickup'),['shipment_id'=>[(int)$providerShipmentId]]); $r->throw(); return (array)$r->json(); }
    public function track(string $providerShipmentId): array { $r=$this->client()->get($this->url('/courier/track/shipment/'.rawurlencode($providerShipmentId))); $r->throw(); return (array)$r->json(); }
    public function cancel(string $providerShipmentId): array { $r=$this->client()->post($this->url('/orders/cancel'),['ids'=>[(int)$providerShipmentId]]); $r->throw(); return (array)$r->json(); }
}
