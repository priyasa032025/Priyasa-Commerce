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

final class MetaCloudDriver implements
    MessageProviderInterface,
    HealthCheckInterface
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    public function name(): string
    {
        return 'meta';
    }

    public function channel(): string
    {
        return 'whatsapp';
    }

    /**
     * Send generic message.
     */
    public function send(
        SendMessageData $message
    ): MessageResponse {

        $dynamicConfig = $message->payload['whatsapp'] ?? null;

        if (is_array($dynamicConfig) && ! empty($dynamicConfig['template_name'])) {
            return $this->sendDynamicTemplate(
                mobile: $message->mobile,
                config: $dynamicConfig
            );
        }

        if ($message->template !== null) {

            return $this->sendTemplate(
                $message->mobile,
                $message->template,
                $message->variables
            );

        }

        if ($message->otp !== null) {

            return $this->sendOtp(
                $message->mobile,
                $message->otp,
                $message->variables
            );

        }

        return $this->sendText(
            $message->mobile,
            (string) $message->message
        );
    }

    /**
     * Send OTP Template.
     */
    public function sendOtp(
        string $mobile,
        string $otp,
        array $context = []
    ): MessageResponse {

        $template = config(
            'whatsapp.templates.login_otp',
            'login_otp'
        );

        return $this->sendTemplate(
            $mobile,
            $template,
            [
                'otp' => $otp,
            ]
        );
    }

    /**
     * Send Template.
     */
    public function sendTemplate(
        string $mobile,
        string $template,
        array $variables = []
    ): MessageResponse {

        try {

            $response = $this->request(
                $this->templatePayload(
                    $mobile,
                    $template,
                    $variables
                )
            );

            if (! $response->successful()) {

                return MessageResponse::failed(
                    provider: $this->name(),
                    channel: $this->channel(),
                    errorCode: (string) $response->status(),
                    errorMessage: $response->body()
                );
            }

            return MessageResponse::success(
                provider: $this->name(),
                channel: $this->channel(),
                messageId: data_get(
                    $response->json(),
                    'messages.0.id'
                )
            );

        } catch (Throwable $e) {

            Log::error(
                'Meta Cloud API Error',
                [
                    'message' => $e->getMessage(),
                ]
            );

            return MessageResponse::failed(
                provider: $this->name(),
                channel: $this->channel(),
                errorCode: 'META_EXCEPTION',
                errorMessage: $e->getMessage()
            );

        }
    }

    /**
     * Plain text.
     */
    public function sendText(
        string $mobile,
        string $message
    ): MessageResponse {

        return MessageResponse::failed(
            provider: $this->name(),
            channel: $this->channel(),
            errorCode: 'TEXT_NOT_SUPPORTED',
            errorMessage: 'Meta Cloud requires approved templates.'
        );
    }

    /**
     * Media.
     */
    public function sendMedia(
        string $mobile,
        string $url,
        string $caption = ''
    ): MessageResponse {

        return MessageResponse::failed(
            provider: $this->name(),
            channel: $this->channel(),
            errorCode: 'NOT_IMPLEMENTED',
            errorMessage: 'Media API will be added later.'
        );
    }

    /**
     * Health Check.
     */
    public function healthCheck(): bool
    {
        return filled(config('whatsapp.meta.access_token'))
            && filled(config('whatsapp.meta.phone_number_id'))
            && filled(config('whatsapp.meta.base_url'));
    }

    /**
     * Send a fully dynamic WhatsApp template payload.
     */
    public function sendDynamicTemplate(
        string $mobile,
        array $config
    ): MessageResponse {

        try {
            $response = $this->request(
                $this->dynamicTemplatePayload(
                    mobile: $mobile,
                    config: $config
                )
            );

            if (! $response->successful()) {
                return MessageResponse::failed(
                    provider: $this->name(),
                    channel: $this->channel(),
                    errorCode: (string) $response->status(),
                    errorMessage: $response->body()
                );
            }

            return MessageResponse::success(
                provider: $this->name(),
                channel: $this->channel(),
                messageId: data_get($response->json(), 'messages.0.id')
            );
        } catch (Throwable $e) {
            Log::error('Meta Cloud API Error', [
                'message' => $e->getMessage(),
            ]);

            return MessageResponse::failed(
                provider: $this->name(),
                channel: $this->channel(),
                errorCode: 'META_EXCEPTION',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function ping(): bool
    {
        return $this->healthCheck();
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
        return config(
            'whatsapp.meta.version',
            'v23.0'
        );
    }

    public function capabilities(): array
    {
        return [
            'sendOtp',
            'sendTemplate',
            'webhook',
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

    /**
     * Send request.
     */
    protected function request(
        array $payload
    ): Response {

        $pendingRequest = $this->http
            ->timeout(config('whatsapp.timeout'))
            ->withToken(
                config('whatsapp.meta.access_token')
            );

        if (! config('whatsapp.verify_ssl', true)) {
            $pendingRequest = $pendingRequest->withoutVerifying();
        }

        return $pendingRequest
            ->post(
                sprintf(
                    '%s/%s/messages',
                    config('whatsapp.meta.base_url'),
                    config('whatsapp.meta.phone_number_id')
                ),
                $payload
            );

    }

    /**
     * Build template payload.
     */
    protected function templatePayload(
        string $mobile,
        string $template,
        array $variables
    ): array {

        $otp = (string) ($variables['otp'] ?? reset($variables));

        $components = [[
            'type' => 'body',
            'parameters' => [[
                'type' => 'text',
                'text' => $otp,
            ]],
        ]];

        if (config('whatsapp.templates.authentication_button', true)) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [[
                    'type' => 'text',
                    'text' => $otp,
                ]],
            ];
        }

        return [

            'messaging_product' => 'whatsapp',

            'to' => $this->formatMobile($mobile),

            'type' => 'template',

            'template' => [

                'name' => $template,

                'language' => [

                    'code' => config(
                        'whatsapp.default_language',
                        'en'
                    ),

                ],

                'components' => $components,

            ],

        ];

    }

    /**
     * Build a dynamic template payload from request config.
     */
    protected function dynamicTemplatePayload(
        string $mobile,
        array $config
    ): array {

        $components = [];

        foreach (($config['components'] ?? []) as $component) {
            if (is_array($component)) {
                $components[] = $component;
            }
        }

        if (empty($components)) {
            $body = $config['body']['parameters'] ?? [];

            if (! empty($body)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(
                        static fn ($value) => [
                            'type' => 'text',
                            'text' => (string) $value,
                        ],
                        array_values($body)
                    ),
                ];
            }
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatMobile($mobile),
            'type' => 'template',
            'template' => [
                'name' => (string) ($config['template_name'] ?? ''),
                'language' => [
                    'code' => (string) ($config['language'] ?? config('whatsapp.default_language', 'en_US')),
                ],
                'components' => $components,
            ],
        ];
    }

    protected function formatMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);

        return strlen((string) $digits) === 10
            ? '91'.$digits
            : (string) $digits;
    }
}
