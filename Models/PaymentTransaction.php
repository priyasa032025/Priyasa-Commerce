<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class PaymentTransaction extends PriyasaModel { protected $table='priyasa_payment_transactions'; protected $guarded=[]; protected $casts=['payload'=>'array']; }
