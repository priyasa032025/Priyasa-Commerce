<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class OutboxEvent extends PriyasaModel { protected $table='priyasa_outbox_events'; protected $guarded=[]; protected $casts=['payload'=>'array','occurred_at'=>'datetime','published_at'=>'datetime']; }
