<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class NotificationInbox extends PriyasaModel {
    protected $table='priyasa_notification_inbox';
    protected $guarded=[];
    protected $casts=['data'=>'array','read_at'=>'datetime','expires_at'=>'datetime'];
}
