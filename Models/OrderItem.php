<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class OrderItem extends PriyasaModel
{
    protected $table = 'priyasa_order_items';
    protected $guarded = []; protected $casts = ['quantity'=>'integer','unit_price'=>'decimal:2','tax_amount'=>'decimal:2','line_total'=>'decimal:2','snapshot'=>'array']; public function order(){return $this->belongsTo(Order::class);} public function variant(){return $this->belongsTo(ProductVariant::class);}
}
