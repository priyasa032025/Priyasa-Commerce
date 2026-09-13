<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyOTPRequest extends FormRequest
{
    /**
     * Allow request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation Rules.
     */
    public function rules(): array
    {
        return [

            /*
             * Mobile Number
             */

            'mobile' => [

                'required',

                'string',

                'regex:/^[6-9]\d{9}$/',

            ],

            /*
             * OTP
             */

            'otp' => [

                'required',

                'digits:' . config('otp.length', 6),

            ],

            /*
             * Request UUID
             */

            'request_id' => [

                'required',

                'uuid',

            ],

            /*
             * Platform
             */

            'platform' => [

                'nullable',

                'string',

                'in:android,woocommerce,ios,web',

            ],

            /*
             * Device ID
             */

            'device_id' => [

                'nullable',

                'string',

                'max:255',

            ],

            /*
             * Device Token
             */

            'device_token' => [

                'nullable',

                'string',

                'max:500',

            ],

            /*
             * App Version
             */

            'app_version' => [

                'nullable',

                'string',

                'max:50',

            ],

        ];
    }

    /**
     * Prepare request.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([

            'mobile' => preg_replace(
                '/\D/',
                '',
                (string)$this->mobile
            ),

            'otp' => trim(
                (string)$this->otp
            ),

            'platform' => $this->platform ?? 'android',

        ]);
    }

    /**
     * Validation Messages.
     */
    public function messages(): array
    {
        return [

            'mobile.required' =>
                'Mobile number is required.',

            'mobile.regex' =>
                'Invalid mobile number.',

            'otp.required' =>
                'OTP is required.',

            'otp.digits' =>
                'Invalid OTP.',

            'request_id.required' =>
                'Request ID is required.',

            'request_id.uuid' =>
                'Invalid request ID.',

        ];
    }

    /**
     * Attributes.
     */
    public function attributes(): array
    {
        return [

            'request_id' => 'OTP Request',

            'device_token' => 'Firebase Token',

            'device_id' => 'Device ID',

        ];
    }

    /*
     |--------------------------------------------------------------------------
     | Helper Methods
     |--------------------------------------------------------------------------
     */

    public function mobile(): string
    {
        return $this->validated('mobile');
    }

    public function otp(): string
    {
        return $this->validated('otp');
    }

    public function requestId(): string
    {
        return $this->validated('request_id');
    }

    public function platform(): string
    {
        return $this->validated('platform');
    }

    public function deviceToken(): ?string
    {
        return $this->validated('device_token');
    }

    public function deviceId(): ?string
    {
        return $this->validated('device_id');
    }
}