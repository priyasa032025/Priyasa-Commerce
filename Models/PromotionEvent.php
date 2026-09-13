<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

final class PromotionEvent extends PriyasaModel
{
    protected $table = 'priyasa_promotion_events';
    protected $guarded = [];
    protected $casts = ['discount_amount'=>'decimal:2','metadata'=>'array'];
}
