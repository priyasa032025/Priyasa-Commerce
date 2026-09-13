<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Collection extends PriyasaModel { protected $table='priyasa_collections'; protected $guarded=[]; protected $casts=['is_active'=>'boolean']; public function products(){return $this->belongsToMany(Product::class,'priyasa_collection_product')->withPivot('sort_order')->orderBy('priyasa_collection_product.sort_order')->orderBy('priyasa_products.id');} }
