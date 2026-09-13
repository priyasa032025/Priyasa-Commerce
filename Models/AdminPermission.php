<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

final class AdminPermission extends PriyasaModel
{
    protected $table='priyasa_admin_permissions';
    protected $guarded=[];
}
