<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItAwarenessRecord extends Model
{
    protected $fillable = [
        'ambassador_id',
        'branch_id',
        'awareness_date',
        'document_name',
        'document_path',
        'created_by',
    ];

    // The storage path is never sent to the browser; documents are served by the download route.
    protected $hidden = ['document_path'];

    protected function casts(): array
    {
        return [
            'awareness_date' => 'date:Y-m-d',
        ];
    }

    public function ambassador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ambassador_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}