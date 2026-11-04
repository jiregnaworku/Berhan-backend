<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_name',
    'branch_location',
    'ambassador_name',
    'ambassador_phone',
    'backup_name',
    'backup_phone',
    'manager_name',
    'manager_phone',
    'assistant_name',
    'assistant_mobile',
    'display_tv_ip',
    'kaspersky_server_ip',
    'working_computers',
])]
class Branch extends Model
{
    protected function casts(): array
    {
        return [
            'working_computers' => 'integer',
        ];
    }
}
