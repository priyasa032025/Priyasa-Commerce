<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class ProductAttributeOption extends PriyasaModel
{
    protected $table = 'priyasa_attribute_options';
    protected $guarded = [];
    protected $casts = ['is_active'=>'boolean'];

    public function attribute() { return $this->belongsTo(ProductAttribute::class,'attribute_id'); }
    public function variants() { return $this->belongsToMany(ProductVariant::class,'priyasa_variant_attribute_options','attribute_option_id','variant_id')->withTimestamps(); }
}
