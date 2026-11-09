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
    'kaspersky_status',
    'remark',
])]
class ComputerWorkbook extends Model
{
    protected $appends = ['endpoint'];

    public function getEndpointAttribute(): ?string
    {
        return $this->endpoint_protection;
    }

    public function setEndpointAttribute(?string $value): void
    {
        $this->attributes['endpoint_protection'] = $value;
    }
}
