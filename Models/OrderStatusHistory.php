<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class OrderStatusHistory extends PriyasaModel
{
    protected $table = 'priyasa_order_status_history';
    protected $guarded = []; protected $casts = ['metadata'=>'array']; public function order(){return $this->belongsTo(Order::class);}
}
