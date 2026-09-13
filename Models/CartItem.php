<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class CartItem extends PriyasaModel
{
    protected $table = 'priyasa_cart_items';
    protected $guarded = []; protected $casts = ['quantity'=>'integer','unit_price'=>'decimal:2']; public function cart(){return $this->belongsTo(Cart::class);} public function variant(){return $this->belongsTo(ProductVariant::class);}
}
