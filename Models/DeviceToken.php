<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class DeviceToken extends PriyasaModel
{
    protected $table = 'device_tokens';

    protected $casts = [
        'is_active' => 'boolean',
        'status' => 'boolean',
        'last_active' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_failed_at',
        'disabled_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'device_id',
        'phone_number',
        'guest_id',
        'token',
        'platform',
        'browser',
        'device',
        'language',
        'timezone',
        'permission',
        'last_active',
        'status',
        'is_active',
        'last_seen_at',
        'last_failed_at',
        'disabled_at' => 'datetime',
    ];
}
