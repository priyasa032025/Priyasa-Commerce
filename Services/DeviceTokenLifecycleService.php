<?php

namespace Modules\PriyasaCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\DeviceToken;

class DeviceTokenLifecycleService
{
    public function register(array $data): DeviceToken
    {
        return DB::connection('priyasa')->transaction(function () use ($data) {
            $token = trim((string) ($data['token'] ?? ''));
            $deviceId = trim((string) ($data['device_id'] ?? ''));

            if ($token === '' && $deviceId === '') {
                throw new \InvalidArgumentException('token or device_id is required');
            }

            $device = null;

            if ($deviceId !== '') {
                $device = DeviceToken::where('device_id', $deviceId)->first();
            }

            if (! $device && $token !== '') {
                $device = DeviceToken::where('token', $token)->first();
            }

            if (! $device) {
                $device = new DeviceToken();
            }

            if ($deviceId !== '') {
                $device->device_id = $deviceId;
            }

            if ($token !== '') {
                $device->token = $token;
            }

            $device->user_id = $data['user_id'] ?? $device->user_id;
            $device->phone_number = $this->phone($data['phone_number'] ?? null);
            $device->guest_id = $data['guest_id'] ?? $device->guest_id;
            $device->platform = $data['platform'] ?? $device->platform;
            $device->device = $data['device'] ?? $device->device;
            $device->browser = $data['browser'] ?? $device->browser;
            $device->timezone = $data['timezone'] ?? $device->timezone;
            $device->permission = $data['permission'] ?? $device->permission;
            $device->channel = $data['channel'] ?? $device->channel;
            $device->delivery_target = $data['delivery_target'] ?? $device->delivery_target;
            $device->source = $data['source'] ?? $device->source;

            $device->last_seen_at = now();
            $device->last_active = now();
            $device->is_active = true;
            $device->status = 1;

            $device->save();

            return $device->fresh();
        });
    }

    public function refresh(array $data): DeviceToken
    {
        return $this->register($data);
    }

    public function updateDevice(array $data): DeviceToken
    {
        return DB::connection('priyasa')->transaction(function () use ($data) {
            $deviceId = trim((string) ($data['device_id'] ?? ''));
            $token = trim((string) ($data['token'] ?? ''));

            $device = null;

            if ($deviceId !== '') {
                $device = DeviceToken::where('device_id', $deviceId)->first();
            }

            if (! $device && $token !== '') {
                $device = DeviceToken::where('token', $token)->first();
            }

            if (! $device) {
                $device = new DeviceToken();
            }

            if ($deviceId !== '') {
                $device->device_id = $deviceId;
            }

            if ($token !== '') {
                $device->token = $token;
            }

            if (array_key_exists('user_id', $data)) {
                $device->user_id = $data['user_id'] ?? null;
            }

            if (array_key_exists('phone_number', $data)) {
                $device->phone_number = $this->phone($data['phone_number'] ?? null);
            }

            $device->last_active = now();
            $device->last_seen_at = now();
            $device->is_active = true;
            $device->status = 1;

            $device->save();

            return $device->fresh();
        });
    }

    public function logout(array $data): void
    {
        $query = DeviceToken::query();
        if (array_key_exists('user_id', $data) && $data['user_id'] !== null) {
            $query->where('user_id', $data['user_id']);
        }

        if (! empty($data['token'])) {
            $query->where('token', trim((string) $data['token']));
        } elseif (! empty($data['device_id'])) {
            $query->where('device_id', trim((string) $data['device_id']));
        }

        $query->update([
            'user_id' => null,
            'phone_number' => null,
            'is_active' => false,
            'last_active' => now(),
            'status' => 0,
        ]);
    }

    public function destroyDevice(array $data): void
    {
        $query = DeviceToken::query();
        if (array_key_exists('user_id', $data) && $data['user_id'] !== null) {
            $query->where('user_id', $data['user_id']);
        }

        if (! empty($data['token'])) {
            $query->where('token', trim((string) $data['token']));
        } elseif (! empty($data['device_id'])) {
            $query->where('device_id', trim((string) $data['device_id']));
        }

        $query->delete();
    }

    private function phone(?string $phone): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $phone);

        return $value === '' ? null : $value;
    }
}
