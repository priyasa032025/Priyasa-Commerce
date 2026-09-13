<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

final class AdminRole extends PriyasaModel
{
    protected $table='priyasa_admin_roles';
    protected $guarded=[];
    public function permissions(){return $this->belongsToMany(AdminPermission::class,'priyasa_admin_role_permission','role_id','permission_id');}
}
