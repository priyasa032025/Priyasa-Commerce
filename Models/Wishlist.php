<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Wishlist extends PriyasaModel { protected $table='priyasa_wishlists'; protected $guarded=[]; public function variant(){return $this->belongsTo(ProductVariant::class,'variant_id');} }
