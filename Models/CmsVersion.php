<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
final class CmsVersion extends PriyasaModel {
 protected $table='priyasa_cms_versions'; protected $guarded=[];
 protected $casts=['snapshot'=>'array','published_at'=>'datetime'];
}
