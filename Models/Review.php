<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Review extends PriyasaModel { protected $table='priyasa_reviews'; protected $guarded=[]; protected $casts=['verified_purchase'=>'boolean']; public function product(){return $this->belongsTo(Product::class);} }
