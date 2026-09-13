<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class InventoryReservation extends PriyasaModel { protected $table='priyasa_inventory_reservations'; protected $guarded=[]; protected $casts=['expires_at'=>'datetime','released_at'=>'datetime','committed_at'=>'datetime']; public function variant(){return $this->belongsTo(ProductVariant::class);} public function order(){return $this->belongsTo(Order::class);} }
