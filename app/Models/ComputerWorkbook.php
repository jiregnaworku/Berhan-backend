<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_id',
    'created_by',
    'ambassador_name',
    'ip_address',
    'computer_name',
    'computer_job',
    'operating_system',
    'managed_kasp_ip',
    'version',
    'status',
    'network_agent',
    'endpoint_protection',
    'remark',
])]
class ComputerWorkbook extends Model
{
}
