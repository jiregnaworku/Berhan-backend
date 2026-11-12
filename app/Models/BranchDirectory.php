<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchDirectory extends Model
{
    protected $table = 'branch_directory';

    protected $fillable = ['code', 'name', 'branch_type'];
}