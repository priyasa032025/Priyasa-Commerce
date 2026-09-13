<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class Cart extends PriyasaModel
{
    protected $table = 'priyasa_carts';
    protected $guarded = []; protected $casts = ['last_added_at'=>'datetime']; public function items(){return $this->hasMany(CartItem::class);} public function customer(){return $this->belongsTo(Customer::class);}
}
