<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class Payment extends PriyasaModel
{
    protected $table = 'priyasa_payments';
    protected $guarded = []; protected $casts = ['amount'=>'decimal:2','payload'=>'array','captured_at'=>'datetime']; public function order(){return $this->belongsTo(Order::class);}
 public function refunds(){return $this->hasMany(Refund::class);}
}
