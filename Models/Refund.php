<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Refund extends PriyasaModel { protected $table='priyasa_refunds'; protected $guarded=[]; protected $casts=['amount'=>'decimal:2','payload'=>'array','processed_at'=>'datetime']; public function order(){return $this->belongsTo(Order::class);} public function payment(){return $this->belongsTo(Payment::class);} }
