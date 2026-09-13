<?php

namespace Modules\PriyasaCore\Repositories;

use Carbon\Carbon;
use Modules\PriyasaCore\Models\DeviceToken;

class DeviceRepository
{
    /**
     * Find by token.
     */
    public function findByToken(string $token): ?DeviceToken
    {
        return DeviceToken::where('token', $token)->first();
    }

    /**
     * Find by device id.
     */
    public function findByDeviceId(string $deviceId): ?DeviceToken
    {
        return DeviceToken::where('device_id', $deviceId)->first();
    }

    /**
     * Find all devices of user.
     */
    public function findByUser(int $userId)
    {
        return DeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Create device.
     */
    public function create(array $data): DeviceToken
    {
        return DeviceToken::create($data);
    }

    /**
     * Update device.
     */
    public function update(DeviceToken $device, array $data): bool
    {
        return $device->update($data);
    }

    /**
     * Delete device.
     */
    public function delete(DeviceToken $device): bool
    {
        return $device->delete();
    }

    /**
     * Mark inactive.
     */
    public function deactivate(DeviceToken $device): bool
    {
        return $device->update([
            'is_active' => false,
            'status' => 0,
        ]);
    }

    /**
     * Update last seen.
     */
    public function touch(DeviceToken $device): bool
    {
        return $device->update([
            'last_seen_at' => Carbon::now(),
        ]);
    }

    /**
     * Get active tokens.
     */
    public function activeTokens(): array
    {
        return DeviceToken::where('is_active', true)
            ->pluck('token')
            ->toArray();
    }

    /**
     * Get user tokens.
     */
    public function userTokens(int $userId): array
    {
        return DeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();
    }
}