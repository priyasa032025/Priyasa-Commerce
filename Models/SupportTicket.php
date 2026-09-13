<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class SupportTicket extends PriyasaModel
{
    protected $table = 'priyasa_support_tickets';
    protected $guarded = [];
    protected $casts = ['metadata'=>'array','last_message_at'=>'datetime','first_response_at'=>'datetime','resolved_at'=>'datetime','closed_at'=>'datetime'];
    public function customer(){return $this->belongsTo(Customer::class);}
    public function order(){return $this->belongsTo(Order::class);}
    public function orderItem(){return $this->belongsTo(OrderItem::class);}
    public function messages(){return $this->hasMany(SupportMessage::class,'ticket_id');}
    public function events(){return $this->hasMany(SupportEvent::class,'ticket_id');}
}
