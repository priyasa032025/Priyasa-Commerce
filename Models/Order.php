<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class Order extends PriyasaModel
{
    protected $table = 'priyasa_orders';
    protected $guarded = []; protected $casts = ['subtotal'=>'decimal:2','discount_total'=>'decimal:2','tax_total'=>'decimal:2','shipping_total'=>'decimal:2','grand_total'=>'decimal:2','metadata'=>'array','placed_at'=>'datetime']; public function items(){return $this->hasMany(OrderItem::class);} public function customer(){return $this->belongsTo(Customer::class);} public function shippingAddress(){return $this->belongsTo(Address::class,'shipping_address_id');} public function payment(){return $this->hasOne(Payment::class);} public function statusHistory(){return $this->hasMany(OrderStatusHistory::class);}
 public function shipment(){return $this->hasOne(Shipment::class);}
 public function returns(){return $this->hasMany(ReturnRequest::class);}
 public function paymentTransactions(){return $this->hasMany(PaymentTransaction::class);}
 public function refunds(){return $this->hasMany(Refund::class);}
 public function invoice(){return $this->hasOne(Invoice::class);}
}
