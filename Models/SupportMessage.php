<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class SupportMessage extends PriyasaModel
{
    protected $table = 'priyasa_support_messages';
    protected $guarded = [];
    protected $casts = ['attachments'=>'array','metadata'=>'array','read_at'=>'datetime'];
    public function ticket(){return $this->belongsTo(SupportTicket::class,'ticket_id');}
}
