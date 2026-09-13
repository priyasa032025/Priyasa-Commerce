<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Contracts;

use Modules\PriyasaCore\Enums\MessageChannel;

interface DriverFactoryInterface
{
    /**
     * Get active driver for a channel.
     *
     * Uses database configuration first,
     * then falls back to config files.
     *
     * Example:
     * DriverFactory::driver(MessageChannel::WHATSAPP)
     */
    public function driver(
        MessageChannel $channel
    ): MessageProviderInterface;

    /**
     * Get a specific driver by name.
     *
     * Example:
     * meta
     * aisensy
     * msg91
     * firebase
     */
    public function make(
        MessageChannel $channel,
        string $driver
    ): MessageProviderInterface;

    /**
     * Check if a driver exists.
     */
    public function has(
        MessageChannel $channel,
        string $driver
    ): bool;

    /**
     * Register a custom driver.
     */
    public function extend(
        MessageChannel $channel,
        string $driver,
        callable $resolver
    ): void;

    /**
     * Return all registered drivers.
     *
     * [
     *   'meta',
     *   'aisensy',
     *   'msg91'
     * ]
     */
    public function available(
        MessageChannel $channel
    ): array;

    /**
     * Forget cached drivers.
     */
    public function flush(): void;
}