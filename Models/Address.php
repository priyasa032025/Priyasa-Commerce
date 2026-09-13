<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class Address extends PriyasaModel
{
    protected $table = 'priyasa_addresses';
    protected $guarded = []; protected $casts = ['is_default'=>'boolean']; public function customer(){return $this->belongsTo(Customer::class);}
}
