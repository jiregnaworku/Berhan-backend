<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category',
    'parent_id',
    'entry_type',
    'title',
    'description',
    'contact_name',
    'team_phone',
    'office_phone',
])]
class ItRelatedInfo extends Model
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('title');
    }
}
