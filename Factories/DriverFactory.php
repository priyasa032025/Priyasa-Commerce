<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Factories;

use Modules\PriyasaCore\Contracts\DriverFactoryInterface;
use Modules\PriyasaCore\Contracts\MessageProviderInterface;
use Modules\PriyasaCore\Enums\MessageChannel;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class DriverFactory implements DriverFactoryInterface
{
    /**
     * Custom driver resolvers.
     *
     * @var array<string, callable>
     */
    protected array $extensions = [];

    public function __construct(
        private readonly Container $container
    ) {
    }

    /**
     * Get default driver for channel.
     */
    public function driver(
        MessageChannel $channel
    ): MessageProviderInterface {

        $driver = config(
            "providers.drivers.{$channel->value}"
        );

        return $this->make(
            $channel,
            $driver
        );
    }

    /**
     * Create provider instance.
     */
    public function make(
        MessageChannel $channel,
        string $driver
    ): MessageProviderInterface {

        if (isset($this->extensions[$channel->value][$driver])) {

            return call_user_func(
                $this->extensions[$channel->value][$driver],
                $this->container
            );
        }

        $class = $this->resolve(
            $channel,
            $driver
        );

        return $this->container->make($class);
    }

    /**
     * Driver exists?
     */
    public function has(
        MessageChannel $channel,
        string $driver
    ): bool {

        try {

            $class = $this->resolve($channel, $driver);
            return class_exists($class);

        } catch (\Throwable) {

            return false;

        }
    }

    /**
     * Register custom driver.
     */
    public function extend(
        MessageChannel $channel,
        string $driver,
        callable $resolver
    ): void {

        $this->extensions[$channel->value][$driver] = $resolver;
    }

    /**
     * Available drivers.
     */
    public function available(
        MessageChannel $channel
    ): array {

        return config(
            "providers.supported.{$channel->value}",
            []
        );
    }

    /**
     * Clear runtime extensions.
     */
    public function flush(): void
    {
        $this->extensions = [];
    }

    /**
     * Resolve provider class.
     */
    protected function resolve(
        MessageChannel $channel,
        string $driver
    ): string {

        return match ($channel) {

            MessageChannel::WHATSAPP => match ($driver) {

                'meta' =>
                    \Modules\PriyasaCore\Drivers\WhatsApp\MetaCloudDriver::class,

                'aisensy' =>
                    \Modules\PriyasaCore\Communication\Drivers\WhatsApp\AiSensyDriver::class,

                'interakt' =>
                    \Modules\PriyasaCore\Communication\Drivers\WhatsApp\InteraktDriver::class,

                'gupshup' =>
                    \Modules\PriyasaCore\Communication\Drivers\WhatsApp\GupshupDriver::class,

                'twilio' =>
                    \Modules\PriyasaCore\Communication\Drivers\WhatsApp\TwilioWhatsAppDriver::class,

                'custom' =>
                    \Modules\PriyasaCore\Communication\Drivers\WhatsApp\CustomWhatsAppDriver::class,

                default => throw new InvalidArgumentException(
                    "Unknown WhatsApp driver [$driver]"
                ),
            },

            MessageChannel::SMS => match ($driver) {

                'placeholder' =>
                    \Modules\PriyasaCore\Communication\Drivers\SMS\PlaceholderSMSDriver::class,

                'msg91' =>
                    \Modules\PriyasaCore\Communication\Drivers\SMS\MSG91Driver::class,

                'textlocal' =>
                    \Modules\PriyasaCore\Communication\Drivers\SMS\TextLocalDriver::class,

                'routemobile' =>
                    \Modules\PriyasaCore\Communication\Drivers\SMS\RouteMobileDriver::class,

                'twilio' =>
                    \Modules\PriyasaCore\Communication\Drivers\SMS\TwilioSMSDriver::class,

                'custom' =>
                    \Modules\PriyasaCore\Communication\Drivers\SMS\CustomSMSDriver::class,

                default => throw new InvalidArgumentException(
                    "Unknown SMS driver [$driver]"
                ),
            },

            MessageChannel::FIREBASE => match ($driver) {

                'fcm' =>
                    \Modules\PriyasaCore\Communication\Drivers\Firebase\FirebaseDriver::class,

                default => throw new InvalidArgumentException(
                    "Unknown Firebase driver [$driver]"
                ),
            },

            default => throw new InvalidArgumentException(
                "Unsupported communication channel."
            ),
        };
    }
}