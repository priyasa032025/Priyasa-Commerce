<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\DTO;

final readonly class SendMessageData
{
    public function __construct(

        /**
         * Mobile Number (E.164 preferred)
         * Example: 919876543210
         */
        public string $mobile,

        /**
         * Communication Channel
         * whatsapp | sms | firebase | email
         */
        public string $channel,

        /**
         * Template Name
         */
        public ?string $template = null,

        /**
         * Plain Message
         */
        public ?string $message = null,

        /**
         * OTP Value
         */
        public ?string $otp = null,

        /**
         * Template Variables
         */
        public array $variables = [],

        /**
         * Media URL
         */
        public ?string $mediaUrl = null,

        /**
         * Media Caption
         */
        public ?string $caption = null,

        /**
         * Firebase Device Token
         */
        public ?string $deviceToken = null,

        /**
         * Notification Title
         */
        public ?string $title = null,

        /**
         * Additional Payload
         */
        public array $payload = [],

        /**
         * Request UUID
         */
        public ?string $requestId = null,

        /**
         * Priority
         */
        public int $priority = 1,

        /**
         * Metadata
         */
        public array $meta = [],

    ) {}

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [

            'mobile' => $this->mobile,

            'channel' => $this->channel,

            'template' => $this->template,

            'message' => $this->message,

            'otp' => $this->otp,

            'variables' => $this->variables,

            'media_url' => $this->mediaUrl,

            'caption' => $this->caption,

            'device_token' => $this->deviceToken,

            'title' => $this->title,

            'payload' => $this->payload,

            'request_id' => $this->requestId,

            'priority' => $this->priority,

            'meta' => $this->meta,

        ];
    }
}