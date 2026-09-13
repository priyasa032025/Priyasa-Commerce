<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class OTPRequest extends PriyasaModel
{
    protected $table = 'otp_requests';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
