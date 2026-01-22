<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    protected $fillable = [
        'group_id','pro_bowler_id','source','assigned_at','expires_at'
    ];
    protected $casts = [
        'assigned_at'=>'datetime',
        'expires_at'=>'date',
    ];
}
