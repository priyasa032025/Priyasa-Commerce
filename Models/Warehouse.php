<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Warehouse extends PriyasaModel { protected $table='priyasa_warehouses'; protected $guarded=[]; protected $casts=['supports_fulfillment'=>'boolean']; public function inventory(){return $this->hasMany(WarehouseInventory::class);} }
