<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Contracts;

use Modules\PriyasaCore\DTO\MessageResponse;
use Modules\PriyasaCore\DTO\SendMessageData;

interface MessageProviderInterface
{
    /**
     * Driver name.
     *
     * Example:
     * meta
     * aisensy
     * msg91
     * firebase
     */
    public function name(): string;

    /**
     * Channel.
     *
     * whatsapp
     * sms
     * firebase
     * email
     */
    public function channel(): string;

    /**
     * Check provider availability.
     */
    public function healthCheck(): bool;

    /**
     * Send message.
     */
    public function send(
        SendMessageData $message
    ): MessageResponse;

    /**
     * Send OTP.
     */
    public function sendOtp(
        string $mobile,
        string $otp,
        array $context = []
    ): MessageResponse;

    /**
     * Send template message.
     */
    public function sendTemplate(
        string $mobile,
        string $template,
        array $variables = []
    ): MessageResponse;

    /**
     * Send plain text.
     */
    public function sendText(
        string $mobile,
        string $message
    ): MessageResponse;

    /**
     * Send media.
     */
    public function sendMedia(
        string $mobile,
        string $url,
        string $caption = ''
    ): MessageResponse;

    /**
     * Verify webhook signature.
     */
    public function verifyWebhook(
        array $payload,
        array $headers = []
    ): bool;

    /**
     * Parse incoming webhook.
     */
    public function parseWebhook(
        array $payload
    ): array;

    /**
     * Provider capabilities.
     */
    public function capabilities(): array;
}