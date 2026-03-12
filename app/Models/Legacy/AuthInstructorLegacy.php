<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class AuthInstructorLegacy extends Model
{
    protected $connection = 'mysql_legacy';
    protected $table = 'authinstructor';
    public $timestamps = false;
}