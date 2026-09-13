<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class IdempotencyKey extends PriyasaModel
{
    protected $table = 'priyasa_idempotency_keys';
    protected $guarded = []; protected $casts = ['response'=>'array'];
}
