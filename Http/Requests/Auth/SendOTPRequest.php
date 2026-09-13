<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class SendOTPRequest extends FormRequest
{
    /**
     * Everyone can access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [

            /*
             * Indian Mobile Number
             */

            'mobile' => [

                'required',

                'string',

                'regex:/^[6-9]\d{9}$/',

            ],

            /*
             * Android Device Token
             */

            'device_token' => [

                'nullable',

                'string',

                'max:500',

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
             * Login / Register / Reset Password
             */

            'purpose' => [

                'nullable',

                'string',

                'in:login,register,forgot_password',

            ],

            /*
             * Preferred Channel
             */

            'channel' => [

                'nullable',

                'string',

                'in:auto,whatsapp,sms,firebase',

            ],

            /*
             * Android App Version
             */

            'app_version' => [

                'nullable',

                'string',

                'max:50',

            ],

            /*
             * Platform
             */

            'platform' => [

                'nullable',

                'string',

                'in:android,woocommerce,ios,web',

            ],

        ];
    }

    /**
     * Prepare input.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([

            'mobile' => preg_replace(
                '/\D/',
                '',
                (string) $this->mobile
            ),

            'purpose' => $this->purpose ?? 'login',

            'channel' => $this->channel ?? 'auto',

            'platform' => $this->platform ?? 'android',

        ]);
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [

            'mobile.required' =>
                'Mobile number is required.',

            'mobile.regex' =>
                'Enter a valid 10-digit Indian mobile number.',

            'purpose.in' =>
                'Invalid OTP purpose.',

            'channel.in' =>
                'Invalid communication channel.',

        ];
    }

    /**
     * Custom attributes.
     */
    public function attributes(): array
    {
        return [

            'device_token' => 'Firebase Device Token',

            'device_id' => 'Device ID',

        ];
    }

    /**
     * Extra helper methods.
     */

    public function mobile(): string
    {
        return $this->validated('mobile');
    }

    public function deviceToken(): ?string
    {
        return $this->validated('device_token');
    }

    public function deviceId(): ?string
    {
        return $this->validated('device_id');
    }

    public function purpose(): string
    {
        return $this->validated('purpose');
    }

    public function channel(): string
    {
        return $this->validated('channel');
    }

    public function platform(): string
    {
        return $this->validated('platform');
    }
}