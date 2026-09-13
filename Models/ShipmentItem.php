<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class ShipmentItem extends PriyasaModel { protected $table='priyasa_shipment_items'; protected $guarded=[]; protected $casts=['quantity'=>'integer']; public function shipment(){return $this->belongsTo(Shipment::class);} public function orderItem(){return $this->belongsTo(OrderItem::class);} }
