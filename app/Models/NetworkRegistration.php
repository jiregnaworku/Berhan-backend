<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_name',
    'reason',
    'remark',
    'ethio_telecom_side',
    'ethio_telecom_frequency',
    'elpha_side',
    'elpha_frequency',
    'incident_date',
    'created_by',
])]
class NetworkRegistration extends Model
{
    protected function casts(): array
    {
        return [
            'incident_date' => 'date:Y-m-d',
        ];
    }
}
