<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Drivers\SMS;

use Modules\PriyasaCore\Contracts\HealthCheckInterface;
use Modules\PriyasaCore\Contracts\MessageProviderInterface;
use Modules\PriyasaCore\DTO\HealthCheckResponse;
use Modules\PriyasaCore\DTO\MessageResponse;
use Modules\PriyasaCore\DTO\SendMessageData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MSG91Driver implements
    MessageProviderInterface,
    HealthCheckInterface
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    public function name(): string
    {
        return 'msg91';
    }

    public function channel(): string
    {
        return 'sms';
    }

    public function send(
        SendMessageData $message
    ): MessageResponse {

        if ($message->otp !== null) {
            return $this->sendOtp(
                $message->mobile,
                $message->otp,
                $message->variables
            );
        }

        if ($message->template !== null) {
            return $this->sendTemplate(
                $message->mobile,
                $message->template,
                $message->variables
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

        $message = sprintf(
            "Your %s OTP is %s. Valid for %d minutes.",
            config('app.name'),
            $otp,
            (int)(config('otp.expiry') / 60)
        );

        return $this->sendText(
            $mobile,
            $message
        );
    }

    public function sendTemplate(
        string $mobile,
        string $template,
        array $variables = []
    ): MessageResponse {

        $payload = [

            'template_id' => config('sms.msg91.template_id'),

            'short_url' => '0',

            'recipients' => [[

                'mobiles' => $mobile,

                'var' => $variables,

            ]]

        ];

        return $this->post($payload);
    }

    public function sendText(
        string $mobile,
        string $message
    ): MessageResponse {

        $payload = [

            'sender' => config('sms.msg91.sender_id'),

            'route' => '4',

            'country' => '91',

            'sms' => [[

                'message' => $message,

                'to' => [

                    $mobile

                ]

            ]]

        ];

        return $this->post($payload);
    }

    protected function post(
        array $payload
    ): MessageResponse {

        try {

            $response = $this->request($payload);

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
                    'request_id'
                )

            );

        } catch (Throwable $e) {

            Log::error(
                'MSG91 Error',
                [
                    'exception' => $e->getMessage(),
                ]
            );

            return MessageResponse::failed(

                provider: $this->name(),

                channel: $this->channel(),

                errorCode: 'MSG91_EXCEPTION',

                errorMessage: $e->getMessage()

            );

        }

    }

    protected function request(
        array $payload
    ): Response {

        return $this->http
            ->timeout(config('sms.timeout'))
            ->acceptJson()
            ->withHeaders([

                'authkey' => config('sms.msg91.auth_key'),

                'Content-Type' => 'application/json',

            ])
            ->post(
                config('sms.msg91.base_url') . '/flow',
                $payload
            );

    }

    public function healthCheck(): bool
    {
        try {

            return $this->http
                ->timeout(5)
                ->get(
                    config('sms.msg91.base_url')
                )
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
            status: $this->status(),
            latency: 0,
            version: 'v5',
            lastSuccess: CarbonImmutable::now(),
            capabilities: [
                'sendOtp',
                'sendTemplate',
                'sendText',
            ]
        );
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
        return 'v5';
    }

    public function capabilities(): array
    {
        return [
            'sendOtp',
            'sendTemplate',
            'sendText',
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
    ): bool
    {
        return true;
    }

    public function parseWebhook(
        array $payload
    ): array
    {
        return $payload;
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
            errorMessage: 'SMS media is not supported.'
        );

    }
}