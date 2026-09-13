<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Drivers\WhatsApp;

use Modules\PriyasaCore\Contracts\HealthCheckInterface;
use Modules\PriyasaCore\Contracts\MessageProviderInterface;
use Modules\PriyasaCore\DTO\HealthCheckResponse;
use Modules\PriyasaCore\DTO\MessageResponse;
use Modules\PriyasaCore\DTO\SendMessageData;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AiSensyDriver implements
    MessageProviderInterface,
    HealthCheckInterface
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    public function name(): string
    {
        return 'aisensy';
    }

    public function channel(): string
    {
        return 'whatsapp';
    }

    public function send(
        SendMessageData $message
    ): MessageResponse {

        if ($message->template) {

            return $this->sendTemplate(
                $message->mobile,
                $message->template,
                $message->variables
            );

        }

        if ($message->otp) {

            return $this->sendOtp(
                $message->mobile,
                $message->otp,
                $message->variables
            );

        }

        return $this->sendText(
            $message->mobile,
            (string)$message->message
        );
    }

    public function sendOtp(
        string $mobile,
        string $otp,
        array $context = []
    ): MessageResponse {

        return $this->sendTemplate(
            $mobile,
            config('whatsapp.templates.login_otp'),
            array_merge(
                [
                    'otp' => $otp,
                ],
                $context
            )
        );
    }

    public function sendTemplate(
        string $mobile,
        string $template,
        array $variables = []
    ): MessageResponse {

        try {

            $response = $this->request(
                $mobile,
                $template,
                $variables
            );

            if (! $response->successful()) {

                return MessageResponse::failed(
                    provider: $this->name(),
                    channel: $this->channel(),
                    errorCode: (string)$response->status(),
                    errorMessage: $response->body()
                );

            }

            return MessageResponse::success(
                provider: $this->name(),
                channel: $this->channel(),
                messageId: data_get(
                    $response->json(),
                    'data.messageId'
                )
            );

        } catch (Throwable $exception) {

            Log::error(
                'AiSensy Error',
                [
                    'message' => $exception->getMessage(),
                ]
            );

            return MessageResponse::failed(
                provider: $this->name(),
                channel: $this->channel(),
                errorCode: 'AISENSY_EXCEPTION',
                errorMessage: $exception->getMessage()
            );

        }
    }

    public function sendText(
        string $mobile,
        string $message
    ): MessageResponse {

        return MessageResponse::failed(
            provider: $this->name(),
            channel: $this->channel(),
            errorCode: 'TEXT_NOT_SUPPORTED',
            errorMessage: 'Use approved templates.'
        );
    }

    public function sendMedia(
        string $mobile,
        string $url,
        string $caption = ''
    ): MessageResponse {

        return MessageResponse::failed(
            provider: $this->name(),
            channel: $this->channel(),
            errorCode: 'NOT_IMPLEMENTED',
            errorMessage: 'Media support coming soon.'
        );
    }

    public function healthCheck(): bool
    {
        try {

            return $this->http
                ->timeout(10)
                ->get(config('whatsapp.aisensy.base_url'))
                ->successful();

        } catch (Throwable) {

            return false;

        }
    }

    public function health(): HealthCheckResponse
    {
        return new HealthCheckResponse(
            healthy: $this->healthCheck(),
            provider: $this->name(),
            channel: $this->channel(),
            status: 'active',
            latency: 0
        );
    }

    public function ping(): bool
    {
        return $this->healthCheck();
    }

    public function status(): string
    {
        return $this->healthCheck()
            ? 'active'
            : 'failed';
    }

    public function latency(): int
    {
        return 0;
    }

    public function version(): ?string
    {
        return 'v1';
    }

    public function capabilities(): array
    {
        return [
            'sendOtp',
            'sendTemplate',
        ];
    }

    public function lastSuccessAt(): ?\DateTimeInterface
    {
        return null;
    }

    public function lastFailureAt(): ?\DateTimeInterface
    {
        return null;
    }

    public function failureCount(): int
    {
        return 0;
    }

    public function reset(): void
    {
    }

    public function verifyWebhook(
        array $payload,
        array $headers = []
    ): bool {

        return true;

    }

    public function parseWebhook(
        array $payload
    ): array {

        return $payload;

    }

    protected function request(
        string $mobile,
        string $template,
        array $variables
    ): Response {

        return $this->http
            ->timeout(config('whatsapp.timeout'))
            ->withHeaders([
                'Authorization' => config('whatsapp.aisensy.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post(
                config('whatsapp.aisensy.base_url'),
                [
                    'apiKey' => config('whatsapp.aisensy.api_key'),
                    'campaignName' => $template,
                    'destination' => $mobile,
                    'userName' => config('app.name'),
                    'templateParams' => array_values($variables),
                ]
            );

    }
}
