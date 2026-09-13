<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Contracts;

use Modules\PriyasaCore\DTO\HealthCheckResponse;

interface HealthCheckInterface
{
    /**
     * Check if provider is reachable.
     */
    public function ping(): bool;

    /**
     * Perform complete health check.
     */
    public function health(): HealthCheckResponse;

    /**
     * Get current provider status.
     *
     * active
     * degraded
     * failed
     * maintenance
     */
    public function status(): string;

    /**
     * Provider response time in milliseconds.
     */
    public function latency(): int;

    /**
     * Provider version.
     */
    public function version(): ?string;

    /**
     * Supported features.
     *
     * Example:
     * sendOtp
     * sendTemplate
     * sendMedia
     * webhook
     */
    public function capabilities(): array;

    /**
     * Last successful communication.
     */
    public function lastSuccessAt(): ?\DateTimeInterface;

    /**
     * Last failed communication.
     */
    public function lastFailureAt(): ?\DateTimeInterface;

    /**
     * Total consecutive failures.
     */
    public function failureCount(): int;

    /**
     * Reset provider health state.
     */
    public function reset(): void;
}