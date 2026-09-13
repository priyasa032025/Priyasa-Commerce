<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class InventoryMovement extends PriyasaModel
{
    protected $table = 'priyasa_inventory_movements';
    protected $guarded = []; protected $casts = ['metadata'=>'array'];
}
