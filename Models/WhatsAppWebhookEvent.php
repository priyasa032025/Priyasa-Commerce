<?php
namespace Modules\PriyasaCore\Models; use Modules\PriyasaCore\Models\PriyasaModel;
class WhatsAppWebhookEvent extends PriyasaModel {protected $table='priyasa_whatsapp_webhook_events'; protected $guarded=[]; protected $casts=['payload'=>'array','processed_at'=>'datetime'];}
