<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Shipment extends PriyasaModel { protected $table='priyasa_shipments'; protected $guarded=[]; protected $casts=['metadata'=>'array','shipped_at'=>'datetime','delivered_at'=>'datetime','cancelled_at'=>'datetime','last_synced_at'=>'datetime']; public function order(){return $this->belongsTo(Order::class);} public function items(){return $this->hasMany(ShipmentItem::class);} public function events(){return $this->hasMany(ShipmentEvent::class);} }
