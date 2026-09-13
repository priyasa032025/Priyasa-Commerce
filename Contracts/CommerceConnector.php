<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Contracts;

interface CommerceConnector
{
    public function pullProducts(): array;
    public function pushOrder(array $order): array;
    public function verifyWebhook(string $payload, array $headers): bool;
}
