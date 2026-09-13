<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class SupportEvent extends PriyasaModel
{
    protected $table = 'priyasa_support_events';
    protected $guarded = [];
    protected $casts = ['metadata'=>'array'];
    public function ticket(){return $this->belongsTo(SupportTicket::class,'ticket_id');}
}
