<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingComplianceNotification extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'pro_bowler_training_id',
        'notification_type',
        'expires_on',
        'notice_year',
        'recipient_email',
        'status',
        'sent_at',
        'error_message',
        'requested_by_user_id',
    ];

    protected $casts = [
        'expires_on' => 'date',
        'notice_year' => 'integer',
        'sent_at' => 'datetime',
    ];
}
