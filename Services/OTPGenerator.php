<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class OTPGenerator
{
    /**
     * Generate numeric OTP.
     */
    public function generate(?int $length = null): string
    {
        $length ??= (int) Config::get('otp.length', 6);

        if ($length < 4 || $length > 10) {
            throw new \InvalidArgumentException(
                'OTP length must be between 4 and 10 digits.'
            );
        }

        $otp = '';

        for ($i = 0; $i < $length; $i++) {
            $otp .= (string) random_int(0, 9);
        }

        return $otp;
    }

    /**
     * Hash OTP before database storage.
     */
    public function hash(string $otp): string
    {
        return hash(
            Config::get('otp.hash_algorithm', 'sha256'),
            $otp
        );
    }

    /**
     * Verify OTP.
     */
    public function verify(
        string $plainOtp,
        string $hashedOtp
    ): bool {

        return hash_equals(
            $hashedOtp,
            $this->hash($plainOtp)
        );

    }

    /**
     * Generate Request UUID.
     */
    public function requestId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * OTP Expiry Timestamp.
     */
    public function expiresAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            '+' . config('otp.expiry') . ' seconds'
        );
    }
}