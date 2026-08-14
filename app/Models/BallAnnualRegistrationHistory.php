<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BallAnnualRegistrationHistory extends Model
{
    protected $fillable = [
        'registration_id',
        'action',
        'from_status',
        'to_status',
        'acted_by_user_id',
        'note',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function registration()
    {
        return $this->belongsTo(BallAnnualRegistration::class, 'registration_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }
}
