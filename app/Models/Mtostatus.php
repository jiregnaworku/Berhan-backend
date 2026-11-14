<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_id',
    'mto',
    'status',
    'remark',
    'submitted_by',
    'review_state',
    'accepted_by',
    'accepted_at',
])]
class MtoStatus extends Model
{
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}