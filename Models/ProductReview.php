<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class ProductReview extends PriyasaModel
{
    protected $table = 'priyasa_product_reviews';
    protected $guarded = [];
    protected $casts = ['verified_purchase'=>'boolean','approved_at'=>'datetime','rejected_at'=>'datetime'];
}
