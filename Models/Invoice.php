<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class Invoice extends PriyasaModel { protected $table='priyasa_invoices'; protected $guarded=[]; protected $casts=['subtotal'=>'decimal:2','discount_total'=>'decimal:2','taxable_total'=>'decimal:2','tax_total'=>'decimal:2','shipping_total'=>'decimal:2','grand_total'=>'decimal:2','lines'=>'array','billing_address'=>'array','shipping_address'=>'array','issued_at'=>'datetime']; public function order(){return $this->belongsTo(Order::class);} }
