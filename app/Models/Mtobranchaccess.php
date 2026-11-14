<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['branch_id', 'enabled', 'updated_by'])]
class MtoBranchAccess extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}