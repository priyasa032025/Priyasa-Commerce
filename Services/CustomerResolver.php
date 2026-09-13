<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\PriyasaCore\Models\Customer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single source of truth for mapping the authenticated Laravel User to the
 * PRIYASA commerce Customer record.
 */
final class CustomerResolver
{
    public function resolve(Authenticatable $user): Customer
    {
        if ($user instanceof Customer) {
            // Backward compatibility for Sanctum tokens issued before the
            // identity merge. New logins always authenticate App\Models\User.
            return $user;
        }

        return DB::connection('priyasa')->transaction(function () use ($user): Customer {
            // Serialize customer provisioning per authenticated user so two
            // simultaneous checkout requests cannot create duplicate rows.


            $customer = Customer::query()
                ->where('user_id', $user->getKey())
                ->first();

            if ($customer) {
                return $customer;
            }

            $phone = $this->phone($user);

            if ($phone !== null) {
                $customer = Customer::query()
                    ->whereIn('phone', $this->phoneCandidates($phone))
                    ->first();

                if ($customer) {
                    if ($customer->user_id !== null && (int) $customer->user_id !== (int) $user->getKey()) {
                        throw new RuntimeException('This mobile number is already linked to another customer account.');
                    }

                    $customer->forceFill([
                        'user_id' => $user->getKey(),
                        'phone_verified_at' => $user->mobile_verified_at ?? $customer->phone_verified_at,
                    ])->save();

                    return $customer->fresh();
                }
            }

            [$firstName, $lastName] = $this->splitName((string) ($user->name ?? ''));

            if ($phone === null) {
                throw new RuntimeException('Authenticated user has no mobile number.');
            }

            return Customer::query()->create([
                'user_id' => $user->getKey(),
                'phone' => $phone,
                'email' => $user->email ?: null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_verified_at' => $user->mobile_verified_at ?? null,
                'status' => 'active',
            ]);
        });
    }

    private function phone(User $user): ?string
    {
        $value = trim((string) ($user->mobile ?? $user->phone ?? ''));
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\D+/', '', $value) ?? '';
        return $value === '' ? null : $value;
    }

    /**
     * Return common legacy representations of an Indian mobile number.
     *
     * @return array<int,string>
     */
    private function phoneCandidates(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $candidates = [$digits];
        if (strlen($digits) === 10) {
            $candidates[] = '91'.$digits;
            $candidates[] = '+91'.$digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $candidates[] = substr($digits, 2);
            $candidates[] = '+'.$digits;
        }
        return array_values(array_unique(array_filter($candidates)));
    }

    /** @return array{0:string|null,1:string|null} */
    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['PRIYASA Customer', null];
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $first = array_shift($parts) ?: 'PRIYASA Customer';
        $last = $parts ? implode(' ', $parts) : null;

        return [$first, $last];
    }
}
