<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ResendOTPRequest extends FormRequest
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

            'platform' => $this->platform ?? 'android',

        ]);
    }

    /**
     * Validation Messages.
     */
    public function messages(): array
    {
        return [

            'request_id.required' =>
                'Request ID is required.',

            'request_id.uuid' =>
                'Invalid Request ID.',

        ];
    }

    /**
     * Friendly field names.
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

    public function appVersion(): ?string
    {
        return $this->validated('app_version');
    }
}