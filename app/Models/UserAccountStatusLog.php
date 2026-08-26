<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccountStatusLog extends Model
{
    protected $fillable = [
        'user_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
