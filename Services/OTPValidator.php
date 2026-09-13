<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Modules\PriyasaCore\Models\OTPRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class OTPValidator
{
    public function __construct(
        private readonly OTPGenerator $generator,
    ) {
    }

    /**
     * Validate complete OTP request.
     *
     * @throws ValidationException
     */
    public function validate(
        OTPRequest $request,
        string $enteredOtp
    ): bool {

        $this->ensureNotVerified($request);

        $this->ensureNotExpired($request);

        $this->ensureAttemptsRemaining($request);

        if (! $this->generator->verify(
            $enteredOtp,
            $request->otp_hash
        )) {

            $request->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP.'],
            ]);
        }

        return true;
    }

    /**
     * OTP already verified.
     */
    protected function ensureNotVerified(
        OTPRequest $request
    ): void {

        if ($request->verified_at !== null) {

            throw ValidationException::withMessages([
                'otp' => ['OTP already verified.'],
            ]);

        }
    }

    /**
     * OTP expired.
     */
    protected function ensureNotExpired(
        OTPRequest $request
    ): void {

        if (
            CarbonImmutable::parse($request->expires_at)
                ->isPast()
        ) {

            throw ValidationException::withMessages([
                'otp' => ['OTP has expired.'],
            ]);

        }
    }

    /**
     * Attempts exceeded.
     */
    protected function ensureAttemptsRemaining(
        OTPRequest $request
    ): void {

        if (
            $request->attempts >=
            config('otp.max_attempts')
        ) {

            throw ValidationException::withMessages([
                'otp' => [
                    'Maximum verification attempts exceeded.'
                ],
            ]);

        }
    }

    /**
     * Can resend?
     */
    /**
 * Can resend?
 */
public function canResend(
    OTPRequest $request
): bool {

    if ($request->verified_at !== null) {
        return false;
    }

    $resendAt = $request->created_at
        ->copy()
        ->addSeconds(
            (int) config('otp.resend_after', 30)
        );

    return now()->greaterThanOrEqualTo($resendAt);
}

/**
 * Remaining resend time.
 */
public function resendAfter(
    OTPRequest $request
): int {

    $resendAt = $request->created_at
        ->copy()
        ->addSeconds(
            (int) config('otp.resend_after', 30)
        );

    if (now()->greaterThanOrEqualTo($resendAt)) {
        return 0;
    }

    return (int) ceil(
        now()->diffInSeconds(
            $resendAt,
            false
        )
    );
}
}