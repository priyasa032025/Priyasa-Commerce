<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Referral extends PriyasaModel { protected $table='priyasa_referrals'; protected $guarded=[]; public function referrer(){return $this->belongsTo(Customer::class,'referrer_customer_id');} public function referred(){return $this->belongsTo(Customer::class,'referred_customer_id');} }
