<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class ReturnRequest extends PriyasaModel
{
    protected $table = 'priyasa_returns'; protected $guarded = []; protected $casts = ['refund_amount'=>'decimal:2','metadata'=>'array']; public function order(){return $this->belongsTo(Order::class); } public function customer(){return $this->belongsTo(Customer::class); } public function items(){return $this->hasMany(ReturnItem::class,'return_id');}
}
