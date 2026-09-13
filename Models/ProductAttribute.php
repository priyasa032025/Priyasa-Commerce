<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class ProductAttribute extends PriyasaModel
{
    protected $table = 'priyasa_attributes';
    protected $guarded = [];
    protected $casts = ['is_variation'=>'boolean','is_filterable'=>'boolean','is_active'=>'boolean'];

    public function options() { return $this->hasMany(ProductAttributeOption::class,'attribute_id'); }
    public function products() { return $this->belongsToMany(Product::class,'priyasa_product_attributes','attribute_id','product_id')->withPivot('sort_order')->withTimestamps(); }
}
