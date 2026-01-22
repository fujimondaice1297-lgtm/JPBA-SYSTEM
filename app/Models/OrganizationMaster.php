<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationMaster extends Model
{
    protected $table = 'organization_masters';
    protected $fillable = ['name','url'];
}
