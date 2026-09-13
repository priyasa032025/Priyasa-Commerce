<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class FulfillmentAllocation extends PriyasaModel { protected $table='priyasa_fulfillment_allocations'; protected $guarded=[]; protected $casts=['picked_at'=>'datetime','packed_at'=>'datetime','cancelled_at'=>'datetime']; public function warehouse(){return $this->belongsTo(Warehouse::class);} public function order(){return $this->belongsTo(Order::class);} }
