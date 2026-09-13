<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Modules\PriyasaCore\Contracts\MessageProviderInterface;
use Modules\PriyasaCore\DTO\MessageResponse;
use Modules\PriyasaCore\DTO\SendMessageData;
use Modules\PriyasaCore\Enums\MessageChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CommunicationManager
{
    public function __construct(
        private readonly ProviderManager $providerManager
    ) {
    }

    /**
     * Send a communication.
     */
    public function send(
        SendMessageData $data
    ): MessageResponse {

        return DB::connection('priyasa')->transaction(function () use ($data) {

            return match ($data->channel) {

                'auto' => $this->attemptAuto($data),

                'whatsapp' => $this->sendWhatsApp($data),

                'sms' => $this->sendSms($data),

                'firebase' => $this->sendFirebase($data),

                default => throw new \InvalidArgumentException(
                    'Unsupported channel: '.$data->channel
                ),
            };

        });
    }

    /**
     * WhatsApp
     */
    protected function sendWhatsApp(
        SendMessageData $data
    ): MessageResponse {

        return $this->attempt(
            MessageChannel::WHATSAPP,
            $data
        );
    }

    /**
     * SMS
     */
    protected function sendSms(
        SendMessageData $data
    ): MessageResponse {

        return $this->attempt(
            MessageChannel::SMS,
            $data
        );
    }

    /**
     * Firebase
     */
    protected function sendFirebase(
        SendMessageData $data
    ): MessageResponse {

        return $this->attempt(
            MessageChannel::FIREBASE,
            $data
        );
    }

    /**
     * Dispatch to provider.
     */
    protected function dispatch(
        MessageProviderInterface $provider,
        SendMessageData $data
    ): MessageResponse {

        try {

            $response = $provider->send($data);

            Log::info(
                'Communication sent.',
                [
                    'provider' => $provider->name(),
                    'channel' => $provider->channel(),
                    'success' => $response->success,
                    'request_id' => $data->requestId,
                ]
            );

            return $this->attachRequestId($response, $data->requestId);

        } catch (Throwable $e) {

            Log::error(
                'Communication failed.',
                [
                    'provider' => $provider->name(),
                    'channel' => $provider->channel(),
                    'error' => $e->getMessage(),
                    'request_id' => $data->requestId,
                ]
            );

            return $this->attachRequestId(
                MessageResponse::failed(
                    provider: $provider->name(),
                    channel: $provider->channel(),
                    errorCode: 'COMMUNICATION_FAILED',
                    errorMessage: $e->getMessage()
                ),
                $data->requestId
            );
        }
    }
    
    /**
 * Send with automatic fallback.
 *
 * WhatsApp
 * ↓
 * SMS
 * ↓
 * Firebase
 */
protected function attempt(
    MessageChannel $channel,
    SendMessageData $data
): MessageResponse {

    $lastResponse = null;

    foreach ($this->providerManager->fallback($channel) as $driver) {

        try {

            $provider = $this->providerManager
                ->driver(
                    $channel,
                    $driver
                );

            if (! $provider->healthCheck()) {

                Log::warning(
                    "{$driver} is unhealthy."
                );

                continue;
            }

            $response = $this->attachRequestId(
                $provider->send($data),
                $data->requestId
            );

            if ($response->success) {

                $this->markProviderHealthy($driver);

                return $response;

            }

            $lastResponse = $response;

            $this->markProviderFailed($driver);

        } catch (\Throwable $exception) {

            Log::error(
                'Provider failed.',
                [

                    'driver' => $driver,

                    'channel' => $channel->value,

                    'error' => $exception->getMessage(),

                ]
            );

            $this->markProviderFailed($driver);

        }

    }

        return $this->attachRequestId(
            $lastResponse
                ?? MessageResponse::failed(
                    provider: 'system',
                    channel: $channel->value,
                    errorCode: 'NO_PROVIDER_AVAILABLE',
                    errorMessage: 'No communication provider is available.'
                ),
            $data->requestId
        );
    }

    protected function attemptAuto(SendMessageData $data): MessageResponse
    {
        $lastResponse = null;

        foreach (config('providers.priority', []) as $channel) {
            $response = $this->attempt(MessageChannel::from($channel), $data);

            if ($response->success) {
                return $response;
            }

            $lastResponse = $response;
        }

        return $this->attachRequestId(
            $lastResponse
                ?? MessageResponse::failed(
                    provider: 'system',
                    channel: 'auto',
                    errorCode: 'NO_PROVIDER_AVAILABLE',
                    errorMessage: 'No communication provider is available.'
                ),
            $data->requestId
        );
    }

    protected function attachRequestId(
        MessageResponse $response,
        ?string $requestId
    ): MessageResponse {

        if ($response->requestId === $requestId) {
            return $response;
        }

        return new MessageResponse(
            success: $response->success,
            provider: $response->provider,
            channel: $response->channel,
            messageId: $response->messageId,
            requestId: $requestId,
            status: $response->status,
            errorCode: $response->errorCode,
            errorMessage: $response->errorMessage,
            metadata: $response->metadata,
            sentAt: $response->sentAt
        );
    }
    
    protected function markProviderHealthy(
    string $driver
): void {

    $this->providerManager
        ->cacheHealth(
            $driver,
            true
        );

    }
    
    protected function markProviderFailed(
    string $driver
): void {

    $this->providerManager
        ->cacheHealth(
            $driver,
            false
        );

    }
}
