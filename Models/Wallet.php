<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Wallet extends PriyasaModel { protected $table='priyasa_wallets'; protected $guarded=[]; protected $casts=['balance'=>'decimal:2']; public function customer(){return $this->belongsTo(Customer::class);} public function transactions(){return $this->hasMany(WalletTransaction::class);} }
