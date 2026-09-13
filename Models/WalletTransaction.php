<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class WalletTransaction extends PriyasaModel { protected $table='priyasa_wallet_transactions'; protected $guarded=[]; protected $casts=['amount'=>'decimal:2','balance_after'=>'decimal:2','metadata'=>'array']; public function wallet(){return $this->belongsTo(Wallet::class);} public function customer(){return $this->belongsTo(Customer::class);} }
