<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LoginResource extends JsonResource
{
    /**
     * Transform the login response.
     */
    public function toArray(Request $request): array
    {
        $user = $this['user'];
        $customer = $this['customer'] ?? null;

        return [

            'success' => true,

            'message' => 'Login successful.',

            'token' => $this['token'],

            'token_type' => 'Bearer',

            'request_id' => $this['request_id'] ?? null,

            'customer_id' => $customer?->id,

            'user' => [

                'id' => $user->id,

                'name' => $user->name,

                'email' => $user->email,

                'mobile' => $user->mobile,

                'avatar' => $user->avatar ?? null,

                'mobile_verified' => !is_null($user->mobile_verified_at),

                'mobile_verified_at' => $user->mobile_verified_at,

                'email_verified' => !is_null($user->email_verified_at),

                'email_verified_at' => $user->email_verified_at,

                'status' => $user->status ?? 'active',

                'created_at' => $user->created_at,

            ],

            'roles' => method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->values()
                : [],

            'permissions' => method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()
                    ->pluck('name')
                    ->values()
                : [],

            'profile' => [

                'completion' => $this->profileCompletion($user),

                'is_complete' => $this->profileCompletion($user) >= 100,

            ],

            'features' => [

                'whatsapp_login' => true,

                'push_notifications' => true,

                'wallet' => true,

                'wishlist' => true,

                'orders' => true,

                'returns' => true,

                'coupons' => true,

            ],

            'timestamp' => now()->toISOString(),

        ];
    }

    /**
     * Calculate profile completion.
     */
    private function profileCompletion(object $user): int
    {
        $score = 0;

        if (!empty($user->name)) {
            $score += 20;
        }

        if (!empty($user->mobile)) {
            $score += 20;
        }

        if (!empty($user->email)) {
            $score += 20;
        }

        if (!empty($user->avatar)) {
            $score += 20;
        }

        if (!empty($user->address)) {
            $score += 20;
        }

        return $score;
    }
}