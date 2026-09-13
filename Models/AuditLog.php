<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class AuditLog extends PriyasaModel
{
    protected $table = 'priyasa_audit_logs';
    protected $guarded = []; protected $casts = ['data'=>'array'];
}
