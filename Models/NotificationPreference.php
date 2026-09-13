<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class NotificationPreference extends PriyasaModel { protected $table='priyasa_notification_preferences'; protected $guarded=[]; protected $casts=['enabled'=>'boolean']; }
