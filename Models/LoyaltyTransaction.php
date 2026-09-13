<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class LoyaltyTransaction extends PriyasaModel { protected $table='priyasa_loyalty_transactions'; protected $guarded=[]; protected $casts=['points'=>'integer','balance_after'=>'integer','metadata'=>'array','expires_at'=>'datetime']; public function account(){return $this->belongsTo(LoyaltyAccount::class,'loyalty_account_id');} }
