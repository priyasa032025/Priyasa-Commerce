<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class LoyaltyAccount extends PriyasaModel { protected $table='priyasa_loyalty_accounts'; protected $guarded=[]; protected $casts=['points'=>'integer']; public function customer(){return $this->belongsTo(Customer::class);} public function transactions(){return $this->hasMany(LoyaltyTransaction::class);} }
