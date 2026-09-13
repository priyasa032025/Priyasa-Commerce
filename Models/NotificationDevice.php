<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class NotificationDevice extends PriyasaModel {
    protected $table='priyasa_notification_devices';
    protected $guarded=[];
    protected $casts=['enabled'=>'boolean','last_seen_at'=>'datetime','invalidated_at'=>'datetime','metadata'=>'array'];
}
