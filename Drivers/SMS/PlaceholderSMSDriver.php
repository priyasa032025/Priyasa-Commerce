<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Drivers\SMS;

use Modules\PriyasaCore\Contracts\HealthCheckInterface;
use Modules\PriyasaCore\Contracts\MessageProviderInterface;
use Modules\PriyasaCore\DTO\HealthCheckResponse;
use Modules\PriyasaCore\DTO\MessageResponse;
use Modules\PriyasaCore\DTO\SendMessageData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final class PlaceholderSMSDriver implements
    MessageProviderInterface,
    HealthCheckInterface
{
    public function name(): string
    {
        return 'placeholder';
    }

    public function channel(): string
    {
        return 'sms';
    }

    public function send(
        SendMessageData $message
    ): MessageResponse {

        if ($message->otp !== null) {
            $response = $this->sendOtp(
                $message->mobile,
                $message->otp,
                $message->variables
            );

            return new MessageResponse(
                success: $response->success,
                provider: $response->provider,
                channel: $response->channel,
                messageId: $response->messageId,
                requestId: $message->requestId,
                status: $response->status,
                errorCode: $response->errorCode,
                errorMessage: $response->errorMessage,
                metadata: $response->metadata,
                sentAt: $response->sentAt
            );
        }

        return $this->sendText(
            $message->mobile,
            (string) $message->message
        );
    }

    public function sendOtp(
        string $mobile,
        string $otp,
        array $context = []
    ): MessageResponse {

        Log::channel('daily')->info(
            'Placeholder SMS OTP',
            [
                'mobile' => $mobile,
                'otp' => $otp,
                'context' => $context,
            ]
        );

        return MessageResponse::success(
            provider: $this->name(),
            channel: $this->channel(),
            messageId: uniqid('sms_', true),
            metadata: [
                'mode' => 'placeholder',
                'logged' => true,
            ]
        );
    }

    public function sendTemplate(
        string $mobile,
        string $template,
        array $variables = []
    ): MessageResponse {

        Log::channel('daily')->info(
            'Placeholder SMS Template',
            [
                'mobile' => $mobile,
                'template' => $template,
                'variables' => $variables,
            ]
        );

        return MessageResponse::success(
            provider: $this->name(),
            channel: $this->channel(),
            messageId: uniqid('sms_', true)
        );
    }

    public function sendText(
        string $mobile,
        string $message
    ): MessageResponse {

        Log::channel('daily')->info(
            'Placeholder SMS',
            [
                'mobile' => $mobile,
                'message' => $message,
            ]
        );

        return MessageResponse::success(
            provider: $this->name(),
            channel: $this->channel(),
            messageId: uniqid('sms_', true)
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
            errorCode: 'NOT_SUPPORTED',
            errorMessage: 'Placeholder driver does not support media.'
        );
    }

    public function healthCheck(): bool
    {
        return true;
    }

    public function ping(): bool
    {
        return true;
    }

    public function health(): HealthCheckResponse
    {
        return new HealthCheckResponse(
            healthy: true,
            provider: $this->name(),
            channel: $this->channel(),
            status: 'active',
            latency: 0,
            version: '1.0',
            message: 'Placeholder SMS driver is active.',
            lastSuccess: CarbonImmutable::now(),
            capabilities: [
                'sendOtp',
                'sendText',
                'sendTemplate',
            ]
        );
    }

    public function status(): string
    {
        return 'active';
    }

    public function latency(): int
    {
        return 0;
    }

    public function version(): ?string
    {
        return '1.0';
    }

    public function capabilities(): array
    {
        return [
            'sendOtp',
            'sendText',
            'sendTemplate',
        ];
    }

    public function lastSuccessAt(): ?\DateTimeInterface
    {
        return CarbonImmutable::now();
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
}
