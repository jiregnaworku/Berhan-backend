<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_id',
    'computer_workbook_id',
    'created_by',
    'network_agent',
    'endpoint',
    'status',
])]
class KasperskyStatus extends Model
{
}
