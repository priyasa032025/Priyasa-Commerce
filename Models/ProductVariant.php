<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class ProductVariant extends PriyasaModel
{
    protected $table = 'priyasa_product_variants';
    protected $guarded = []; protected $casts = ['attributes'=>'array','is_active'=>'boolean']; public function product(){return $this->belongsTo(Product::class);} public function inventory(){return $this->hasOne(Inventory::class,'variant_id');} public function inventoryMovements(){return $this->hasMany(InventoryMovement::class,'variant_id');} public function attributeOptions(){return $this->belongsToMany(ProductAttributeOption::class,'priyasa_variant_attribute_options','variant_id','attribute_option_id')->withTimestamps();}
}
