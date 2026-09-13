<?php

declare(strict_types=1);


namespace Modules\PriyasaCore\Services;

use Modules\PriyasaCore\DTO\MessageResponse;
use Modules\PriyasaCore\DTO\SendMessageData;
use Modules\PriyasaCore\Models\OTPRequest;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\DeviceTokenLifecycleService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OTPService
{
    public function __construct(
        private readonly OTPGenerator $generator,
        private readonly OTPValidator $validator,
        private readonly RateLimiter $rateLimiter,
        private readonly CommunicationManager $communication,
        private readonly DeviceTokenLifecycleService $devices,
    ) {
    }

    /**
     * Send Login OTP.
     */
    public function sendLoginOtp(
        string $mobile,
        ?string $deviceToken = null,
        ?string $deviceId = null,
        ?string $ip = null,
        ?string $channel = null
    ): MessageResponse {

        $this->rateLimiter->check(
            mobile: $mobile,
            ip: $ip,
            deviceId: $deviceId
        );

        return DB::connection('priyasa')->transaction(function () use (
            $mobile,
            $deviceToken,
            $deviceId,
            $ip,
            $channel
        ) {

            $otp = $this->generator->generate();

            $requestId = $this->generator->requestId();

            $request = OTPRequest::create([

                'request_id' => $requestId,

                'mobile' => $mobile,

                'otp_hash' => $this->generator->hash($otp),

                'purpose' => 'login',

                'channel' => $channel ?: 'auto',

                'status' => 'pending',

                'attempts' => 0,

                'expires_at' => CarbonImmutable::instance(
                    $this->generator->expiresAt()
                ),

                'device_id' => $deviceId,

                'device_token' => $deviceToken,

                'ip_address' => $ip,

            ]);

            $response = $this->communication->send(

                new SendMessageData(

                    channel: $channel ?: 'auto',

                    mobile: $mobile,

                    otp: $otp,

                    deviceToken: $deviceToken,

                    requestId: $requestId,

                    variables: [
                        'otp' => $otp,
                    ],

                    meta: [
                        'type' => 'login',
                    ]

                )

            );

            $response = $this->withRequestId(
                $response,
                $requestId
            );

            if ($response->success) {

                $request->update([

                    'provider' => $response->provider,

                    'provider_message_id' => $response->messageId,

                    'status' => 'sent',

                    'sent_at' => now(),

                ]);

            } else {

                $request->update([

                    'status' => 'failed',

                    'failure_reason' => $response->errorMessage,

                ]);

            }

            $this->rateLimiter->hit(
                mobile: $mobile,
                ip: $ip,
                deviceId: $deviceId
            );

            Log::info('OTP Request Created', [

                'request_id' => $requestId,

                'mobile' => $mobile,

                'provider' => $response->provider,

            ]);

            return $response;

        });

    }
    
        /**
     * Verify Login OTP.
     */
    public function verifyLoginOtp(
        string $mobile,
        string $otp,
        string $requestId,
        ?string $deviceToken = null,
        ?string $deviceId = null,
        ?string $platform = null
    ): array {

        $request = OTPRequest::query()
            ->where('request_id', $requestId)
            ->where('mobile', $mobile)
            ->latest()
            ->first();

        if (! $request) {

            throw ValidationException::withMessages([
                'otp' => ['OTP request not found.'],
            ]);

        }

        $this->validator->validate(
            $request,
            $otp
        );

        $request->update([

            'verified_at' => now(),

            'status' => 'verified',

        ]);

        $user = User::firstOrCreate(

            [
                'mobile' => $mobile,
            ],

            [
                'name' => 'User '.substr($mobile, -4),

                'password' => bcrypt(Str::random(40)),

                'mobile_verified_at' => now(),

            ]

        );

        if (! $user->mobile_verified_at) {

            $user->forceFill([

                'mobile_verified_at' => now(),

            ])->save();

        }

        // Create/link the commerce customer as part of authentication. This
        // removes the old phone-only lookup that caused valid users to fail
        // at cart/checkout/orders.
        $customer = Customer::query()->where('user_id', $user->id)->first();
        if (! $customer) {
            $customer = Customer::query()->whereIn('phone', $this->phoneCandidates($mobile))->first();
        }
        if ($customer) {
            if ($customer->user_id !== null && (int) $customer->user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'mobile' => ['This mobile number is already linked to another account.'],
                ]);
            }
            $customer->forceFill([
                'user_id' => $user->id,
                'phone' => $mobile,
                'phone_verified_at' => now(),
            ])->save();
        } else {
            $parts = preg_split('/\s+/', trim((string) ($user->name ?? ''))) ?: [];
            $customer = Customer::query()->create([
                'user_id' => $user->id,
                'phone' => $mobile,
                'email' => $user->email ?: null,
                'first_name' => $parts[0] ?? 'PRIYASA Customer',
                'last_name' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null,
                'phone_verified_at' => now(),
                'status' => 'active',
            ]);
        }

        if ($deviceToken || $deviceId) {
            $this->devices->register([
                'device_id' => $deviceId,
                'token' => $deviceToken,
                'user_id' => $user->id,
                'phone_number' => $mobile,
                'platform' => $platform,
                'source' => 'otp-login',
            ]);
        }

        $token = method_exists($user, 'createToken')
            ? $user->createToken('mobile-app')->plainTextToken
            : 'local-token-'.$user->id.'-'.Str::random(40);

        Log::info('OTP Verified', [

            'mobile' => $mobile,

            'user_id' => $user->id,

            'request_id' => $requestId,

        ]);

        return [

            'success' => true,

            'token' => $token,

            'user' => $user,

            'customer' => $customer,

            'request_id' => $requestId,

        ];

    }

    /**
     * Return common phone representations used by legacy customer rows.
     *
     * @return array<int,string>
     */
    private function phoneCandidates(string $mobile): array
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        $candidates = [$digits];
        if (strlen($digits) === 10) {
            $candidates[] = '+91'.$digits;
            $candidates[] = '91'.$digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $candidates[] = substr($digits, 2);
            $candidates[] = '+'.$digits;
        } elseif (strlen($digits) === 12) {
            $candidates[] = '+'.$digits;
        }
        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Resend Login OTP.
     */
    public function resendOtp(
        string $requestId
    ): MessageResponse {

        $request = OTPRequest::where(
            'request_id',
            $requestId
        )->firstOrFail();

        if (! $this->validator->canResend($request)) {

            throw ValidationException::withMessages([

                'otp' => [

                    'Please wait '
                    .$this->validator->resendAfter($request)
                    .' seconds before requesting another OTP.',

                ],

            ]);

        }

        return $this->sendLoginOtp(

            mobile: $request->mobile,

            deviceToken: $request->device_token,

            deviceId: $request->device_id,

            ip: $request->ip_address,

            channel: $request->channel ?? null

        );

    }

    /**
     * Ensure the outgoing response always includes the OTP request ID.
     */
    private function withRequestId(
        MessageResponse $response,
        string $requestId
    ): MessageResponse {

        if ($response->requestId === $requestId) {
            return $response;
        }

        return new MessageResponse(
            success: $response->success,
            provider: $response->provider,
            channel: $response->channel,
            messageId: $response->messageId,
            requestId: $requestId,
            status: $response->status,
            errorCode: $response->errorCode,
            errorMessage: $response->errorMessage,
            metadata: $response->metadata,
            sentAt: $response->sentAt
        );
    }

    /**
     * Mark request as expired.
     */
    public function expire(
        OTPRequest $request
    ): void {

        $request->update([

            'status' => 'expired',

        ]);

    }

    /**
     * Cancel OTP request.
     */
    public function cancel(
        string $requestId
    ): void {

        OTPRequest::where(

            'request_id',
            $requestId

        )->update([

            'status' => 'cancelled',

        ]);

    }

    /**
     * Cleanup expired OTPs.
     */
    public function cleanup(): int
    {

        return OTPRequest::query()

            ->where('expires_at', '<', now())

            ->whereNull('verified_at')

            ->update([

                'status' => 'expired',

            ]);

    }
    
}
