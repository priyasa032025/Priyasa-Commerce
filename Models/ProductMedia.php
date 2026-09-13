<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class ProductMedia extends PriyasaModel { protected $table='priyasa_product_media'; protected $guarded=[]; protected $casts=['metadata'=>'array','is_primary'=>'boolean']; public function product(){return $this->belongsTo(Product::class);} public function variant(){return $this->belongsTo(ProductVariant::class);}}
