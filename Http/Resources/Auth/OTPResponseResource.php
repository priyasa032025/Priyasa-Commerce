<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Resources\Auth;

use Modules\PriyasaCore\DTO\MessageResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OTPResponseResource extends JsonResource
{
    /**
     * Create a new resource instance.
     */
    public function __construct(MessageResponse $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var MessageResponse $response */
        $response = $this->resource;

        return [
            'success' => $response->success,

            'message' => $response->success
                ? 'OTP sent successfully.'
                : ($response->errorMessage ?? 'Unable to send OTP.'),

            'request_id' => $response->requestId
                ?? null,

            'provider' => $response->provider,

            'channel' => $response->channel,

            'status' => $response->success
                ? 'sent'
                : 'failed',

            'expires_in' => config('otp.expiry', 300),

            'expires_at' => now()
                ->addSeconds(config('otp.expiry', 300))
                ->toISOString(),

            'retry_after' => config('otp.resend_after', 30),

            'fallback_used' => (bool) ($response->metadata['fallback_used'] ?? false),

            'message_id' => $response->messageId
                ?? null,

            'metadata' => $response->metadata
                ?? [],

            'error' => $response->success
                ? null
                : [
                    'code' => $response->errorCode
                        ?? 'SEND_FAILED',

                    'message' => $response->errorMessage,

                ],

            'timestamp' => now()->toISOString(),
        ];
    }
}
