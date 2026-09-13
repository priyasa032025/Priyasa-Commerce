<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

final class PromotionCampaign extends PriyasaModel
{
    protected $table = 'priyasa_promotion_campaigns';
    protected $guarded = [];
    protected $casts = [
        'discount_value' => 'decimal:2', 'minimum_cart_value' => 'decimal:2', 'maximum_discount' => 'decimal:2',
        'usage_limit' => 'integer', 'usage_count' => 'integer', 'per_customer_limit' => 'integer',
        'priority' => 'integer', 'stackable' => 'boolean', 'first_order_only' => 'boolean', 'is_active' => 'boolean',
        'rules' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
    ];
}
