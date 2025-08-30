<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class RegisteredBall extends Model
{
    use HasFactory;

    protected $table = 'registered_balls';

    protected $fillable = [
        'license_no',
        'approved_ball_id',
        'serial_number',
        'registered_at',
        'inspection_number', // 検量証番号（任意）
        'expires_at',        // 有効期限（任意／自動算出）
    ];

    protected $casts = [
        'registered_at' => 'date',
        'expires_at'    => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        // 期限の自動算出：検量証がある場合のみ付与。無ければ null
        $calc = function (self $ball) {
            if (!empty($ball->inspection_number) && !empty($ball->registered_at)) {
                $ball->expires_at = Carbon::parse($ball->registered_at)->addYear()->subDay();
            } else {
                $ball->expires_at = null;
            }
        };

        static::creating($calc);
        static::updating($calc);
    }

    /** 選手 */
    public function proBowler()
    {
        // registered_balls.license_no -> pro_bowlers.license_no
        return $this->belongsTo(ProBowler::class, 'license_no', 'license_no');
    }

    /** 承認ボール */
    public function approvedBall()
    {
        return $this->belongsTo(ApprovedBall::class);
    }

    /** 所有者スコープ（ライセンスNo） */
    public function scopeOwnedByLicense($q, string $licenseNo)
    {
        return $q->where('license_no', $licenseNo);
    }
}
