<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class SupportAction extends PriyasaModel
{
    protected $table = 'priyasa_support_actions';
    protected $guarded = [];
    protected $casts = ['payload' => 'array'];
    public function ticket(){ return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
}
