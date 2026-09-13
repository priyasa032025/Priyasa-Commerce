<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

final class Coupon extends PriyasaModel
{
    protected $table = 'priyasa_coupons';
    protected $guarded = [];
    protected $casts = [
        'value'=>'decimal:2','minimum_cart_value'=>'decimal:2','maximum_discount'=>'decimal:2',
        'rules'=>'array','customer_segments'=>'array','terms'=>'array',
        'starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean','stackable'=>'boolean',
        'auto_apply'=>'boolean','first_order_only'=>'boolean','prepaid_only'=>'boolean','priority'=>'integer',
    ];
    public function redemptions() { return $this->hasMany(CouponRedemption::class); }
}
