<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class RateLimiter
{
    /**
     * Check all rate limits before sending OTP.
     *
     * @throws ValidationException
     */
    public function check(
        string $mobile,
        ?string $ip = null,
        ?string $deviceId = null
    ): void {

        $this->checkMobile($mobile);

        if ($ip !== null) {
            $this->checkIp($ip);
        }

        if ($deviceId !== null) {
            $this->checkDevice($deviceId);
        }
    }

    /**
     * Increment all counters after successful request.
     */
    public function hit(
        string $mobile,
        ?string $ip = null,
        ?string $deviceId = null
    ): void {

        $this->increment(
            "otp:mobile:{$mobile}",
            config('communication.rate_limit.mobile_per_hour', 10)
        );

        if ($ip) {
            $this->increment(
                "otp:ip:{$ip}",
                config('communication.rate_limit.ip_per_hour', 30)
            );
        }

        if ($deviceId) {
            $this->increment(
                "otp:device:{$deviceId}",
                config('communication.rate_limit.device_per_hour', 15)
            );
        }
    }

    /**
     * Mobile rate limit.
     */
    protected function checkMobile(string $mobile): void
    {
        $this->ensureAllowed(
            "otp:mobile:{$mobile}",
            config('communication.rate_limit.mobile_per_hour', 10),
            'Too many OTP requests for this mobile number.'
        );
    }

    /**
     * IP rate limit.
     */
    protected function checkIp(string $ip): void
    {
        $this->ensureAllowed(
            "otp:ip:{$ip}",
            config('communication.rate_limit.ip_per_hour', 30),
            'Too many OTP requests from this IP address.'
        );
    }

    /**
     * Device rate limit.
     */
    protected function checkDevice(string $deviceId): void
    {
        $this->ensureAllowed(
            "otp:device:{$deviceId}",
            config('communication.rate_limit.device_per_hour', 15),
            'Too many OTP requests from this device.'
        );
    }

    /**
     * Verify counter.
     */
    protected function ensureAllowed(
        string $key,
        int $limit,
        string $message
    ): void {

        $count = Cache::get($key, 0);

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'otp' => [$message],
            ]);
        }
    }

    /**
     * Increase cache counter.
     */
    protected function increment(
        string $key,
        int $limit
    ): void {

        if (! Cache::has($key)) {

            Cache::put($key, 1, now()->addHour());

            return;
        }

        Cache::increment($key);

        Cache::put(
            $key,
            min(Cache::get($key), $limit),
            now()->addHour()
        );
    }

    /**
     * Reset all limits.
     */
    public function clear(
        string $mobile,
        ?string $ip = null,
        ?string $deviceId = null
    ): void {

        Cache::forget("otp:mobile:{$mobile}");

        if ($ip) {
            Cache::forget("otp:ip:{$ip}");
        }

        if ($deviceId) {
            Cache::forget("otp:device:{$deviceId}");
        }
    }
}