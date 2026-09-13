<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class Category extends PriyasaModel
{
    protected $table = 'priyasa_categories';
    protected $guarded = []; protected $casts = ['is_active'=>'boolean']; public function products(){return $this->hasMany(Product::class);} public function children(){return $this->hasMany(self::class,'parent_id');}
}
