<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Modules\PriyasaCore\Http\Requests\Auth\SendOTPRequest;
use Modules\PriyasaCore\Http\Requests\Auth\VerifyOTPRequest;
use Modules\PriyasaCore\Http\Requests\Auth\ResendOTPRequest;
use Modules\PriyasaCore\Http\Resources\Auth\OTPResponseResource;
use Modules\PriyasaCore\Http\Resources\Auth\LoginResource;
use Modules\PriyasaCore\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

final class OTPController extends Controller
{
    public function __construct(
        private readonly OTPService $otpManager
    ) {
    }

    /**
     * POST /api/v1/auth/send-otp
     */
    public function send(
        SendOTPRequest $request
    ): JsonResponse {

        $response = $this->otpManager->sendLoginOtp(

            mobile: $request->mobile,

            deviceToken: $request->device_token,

            deviceId: $request->device_id,

            ip: $request->ip(),

            channel: $request->channel === 'auto' ? null : $request->channel

        );

        return (new OTPResponseResource($response))
            ->response()
            ->setStatusCode($response->success ? 200 : 422);

    }

    /**
     * POST /api/v1/auth/verify-otp
     */
    public function verify(
        VerifyOTPRequest $request
    ): JsonResponse {

        $login = $this->otpManager->verifyLoginOtp(

            mobile: $request->mobile,

            otp: $request->otp,

            requestId: $request->request_id,

            deviceToken: $request->deviceToken(),

            deviceId: $request->deviceId(),

            platform: $request->platform()

        );

        return (new LoginResource($login))->response();

    }

    /**
     * POST /api/v1/auth/resend-otp
     */
    public function resend(
        ResendOTPRequest $request
    ): JsonResponse {

        $response = $this->otpManager->resendOtp(

            $request->request_id

        );

        return (new OTPResponseResource($response))->response();

    }

    /**
     * DELETE /api/v1/auth/cancel-otp
     */
    public function cancel(
        string $requestId
    ): JsonResponse {

        $this->otpManager->cancel($requestId);

        return response()->json([

            'success' => true,

            'message' => 'OTP request cancelled.'

        ]);

    }

    /**
     * GET /api/v1/auth/health
     */
    public function health(): JsonResponse
    {

        return response()->json([

            'success' => true,

            'service' => 'OTP Service',

            'status' => 'healthy',

            'version' => config('app.version'),

            'timestamp' => now(),

        ]);

    }
}
