<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class ReturnItem extends PriyasaModel { protected $table='priyasa_return_items'; protected $guarded=[]; protected $casts=['quantity'=>'integer','refund_amount'=>'decimal:2','metadata'=>'array']; public function returnRequest(){return $this->belongsTo(ReturnRequest::class,'return_id');} public function orderItem(){return $this->belongsTo(OrderItem::class,'order_item_id');} }
