<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\DTO;

use Carbon\CarbonImmutable;

final readonly class MessageResponse
{
    public function __construct(
        public bool $success,
        public string $provider,
        public string $channel,
        public ?string $messageId = null,
        public ?string $requestId = null,
        public ?string $status = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
        public ?CarbonImmutable $sentAt = null,
    ) {
    }

    public static function success(
        string $provider,
        string $channel,
        ?string $messageId = null,
        ?string $requestId = null,
        array $metadata = []
    ): self {
        return new self(
            success: true,
            provider: $provider,
            channel: $channel,
            messageId: $messageId,
            requestId: $requestId,
            status: 'sent',
            metadata: $metadata,
            sentAt: CarbonImmutable::now(),
        );
    }

    public static function failed(
        string $provider,
        string $channel,
        string $errorCode,
        string $errorMessage,
        array $metadata = []
    ): self {
        return new self(
            success: false,
            provider: $provider,
            channel: $channel,
            status: 'failed',
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            metadata: $metadata,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider' => $this->provider,
            'channel' => $this->channel,
            'message_id' => $this->messageId,
            'request_id' => $this->requestId,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata,
            'sent_at' => $this->sentAt?->toIso8601String(),
        ];
    }
}
