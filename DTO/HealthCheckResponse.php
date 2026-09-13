<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\DTO;

use Carbon\CarbonImmutable;

final readonly class HealthCheckResponse
{
    public function __construct(

        /**
         * Overall Health
         */
        public bool $healthy,

        /**
         * Provider Name
         */
        public string $provider,

        /**
         * Channel
         */
        public string $channel,

        /**
         * Status
         * active
         * degraded
         * failed
         * maintenance
         */
        public string $status,

        /**
         * Response Time (ms)
         */
        public int $latency,

        /**
         * Provider Version
         */
        public ?string $version = null,

        /**
         * Provider Message
         */
        public ?string $message = null,

        /**
         * Consecutive Failures
         */
        public int $failureCount = 0,

        /**
         * Last Success Time
         */
        public ?CarbonImmutable $lastSuccess = null,

        /**
         * Last Failure Time
         */
        public ?CarbonImmutable $lastFailure = null,

        /**
         * Provider Features
         */
        public array $capabilities = [],

        /**
         * Additional Data
         */
        public array $meta = []

    ) {
    }

    /**
     * Convert object to array.
     */
    public function toArray(): array
    {
        return [

            'healthy' => $this->healthy,

            'provider' => $this->provider,

            'channel' => $this->channel,

            'status' => $this->status,

            'latency' => $this->latency,

            'version' => $this->version,

            'message' => $this->message,

            'failure_count' => $this->failureCount,

            'last_success' => $this->lastSuccess?->toIso8601String(),

            'last_failure' => $this->lastFailure?->toIso8601String(),

            'capabilities' => $this->capabilities,

            'meta' => $this->meta,

        ];
    }

    /**
     * Is provider operational?
     */
    public function isHealthy(): bool
    {
        return $this->healthy;
    }
}
