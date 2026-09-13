<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;
use Illuminate\Database\Eloquent\Builder;

final class CmsSection extends PriyasaModel
{
    protected $table = 'priyasa_cms_sections';
    protected $guarded = [];
    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        $now = now();
        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now); })
            ->where(function (Builder $q) use ($now) { $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now); });
    }
}
