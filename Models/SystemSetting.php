<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class SystemSetting extends PriyasaModel { protected $table='priyasa_system_settings'; protected $guarded=[]; protected $casts=['is_public'=>'boolean']; }
