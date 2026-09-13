<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class Inventory extends PriyasaModel
{
    protected $table = 'priyasa_inventory';
    protected $guarded = []; public function variant(){return $this->belongsTo(ProductVariant::class,'variant_id');}
}
