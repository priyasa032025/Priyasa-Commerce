<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\PriyasaCore\Models\PriyasaModel;

class Customer extends PriyasaModel
{
    protected $table = 'priyasa_customers';

    protected $guarded = [];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'marketing_opt_in' => 'boolean',
        'status' => 'string',
        'last_order_at' => 'datetime',
        'lifetime_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresses(): HasMany { return $this->hasMany(Address::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function cart(): HasOne { return $this->hasOne(Cart::class); }
    public function wishlist(): HasMany { return $this->hasMany(Wishlist::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
}
