<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class ShipmentEvent extends PriyasaModel { protected $table='priyasa_shipment_events'; protected $guarded=[]; protected $casts=['occurred_at'=>'datetime','payload'=>'array']; public function shipment(){return $this->belongsTo(Shipment::class);} }
