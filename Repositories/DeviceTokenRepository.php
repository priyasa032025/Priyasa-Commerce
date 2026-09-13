<?php

namespace Modules\PriyasaCore\Repositories;

use Modules\PriyasaCore\Models\DeviceToken;

class DeviceTokenRepository
{
    public function create(array $data): DeviceToken
    {
        return DeviceToken::create($data);
    }

    public function updateByToken(string $token, array $data): bool
    {
        return DeviceToken::where('token', $token)->update($data) > 0;
    }

    public function deleteByToken(string $token): bool
    {
        return DeviceToken::where('token', $token)->delete() > 0;
    }

    public function findByToken(string $token): ?DeviceToken
    {
        return DeviceToken::where('token', $token)->first();
    }

    public function activeTokens(): array
    {
        return DeviceToken::where('status', 1)->get()->toArray();
    }
}
