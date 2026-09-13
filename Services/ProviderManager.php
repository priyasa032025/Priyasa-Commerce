<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Modules\PriyasaCore\Contracts\DriverFactoryInterface;
use Modules\PriyasaCore\Contracts\MessageProviderInterface;
use Modules\PriyasaCore\Enums\MessageChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class ProviderManager
{
    public function __construct(
        private DriverFactoryInterface $factory
    ) {
    }

    /**
     * Get active provider.
     */
    public function active(
        MessageChannel $channel
    ): MessageProviderInterface {

        $driver = config(
            "providers.drivers.{$channel->value}"
        );

        return $this->factory->make(
            $channel,
            $driver
        );
    }

    /**
     * Get all available providers.
     */
    public function providers(
        MessageChannel $channel
    ): Collection {

        return collect(
            config(
                "providers.supported.{$channel->value}",
                []
            )
        );
    }

    /**
     * Select first healthy provider.
     */
    public function healthy(
        MessageChannel $channel
    ): MessageProviderInterface {

        foreach (
            $this->providers($channel)
            as $driver
        ) {

            $provider = $this->factory->make(
                $channel,
                $driver
            );

            if ($provider->healthCheck()) {
                return $provider;
            }
        }

        throw new \RuntimeException(
            "No healthy provider available for {$channel->value}"
        );
    }

    /**
     * Get fallback providers.
     */
    public function fallback(
        MessageChannel $channel
    ): array {

        if (! config("providers.channels.{$channel->value}", true)) {
            return [];
        }

        return config(
            "providers.supported.{$channel->value}",
            []
        );
    }

    public function driver(
        MessageChannel $channel,
        string $driver
    ): MessageProviderInterface {

        return $this->factory->make(
            $channel,
            $driver
        );
    }

    /**
     * Cache provider health.
     */
    public function cacheHealth(
        string $provider,
        bool $healthy
    ): void {

        Cache::put(
            "provider-health:{$provider}",
            $healthy,
            now()->addMinutes(1)
        );
    }

    /**
     * Read cached health.
     */
    public function isHealthy(
        string $provider
    ): bool {

        return Cache::get(
            "provider-health:{$provider}",
            true
        );
    }

    /**
     * Forget cached provider state.
     */
    public function clearHealth(
        string $provider
    ): void {

        Cache::forget(
            "provider-health:{$provider}"
        );
    }
}
