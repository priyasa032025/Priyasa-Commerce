<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class WebhookEvent extends PriyasaModel { protected $table='priyasa_webhook_events'; protected $guarded=[]; protected $casts=['payload'=>'array']; }
