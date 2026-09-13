<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Services;
use Modules\PriyasaCore\Contracts\PaymentGateway;
use Modules\PriyasaCore\Integrations\Payments\RazorpayGateway;
use RuntimeException;
final class GatewayManager { public function payment(?string $name=null): PaymentGateway { return match($name ?: config('priyasacore.payment_provider','razorpay')) { 'razorpay'=>app(RazorpayGateway::class), default=>throw new RuntimeException('Unsupported payment provider.') }; } }
