<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',                 // ← 過去の名残として残す場合のみ
        'role',                     // ← 新ロール管理ここで追加
        'pro_bowler_id',
        'pro_bowler_license_no',
        'license_no',
        'account_status',
        'setup_link_sent_at',
        'password_set_at',
        'suspended_at',
        'closed_at',
        'account_status_note',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean', // ← これは残しても動作には問題なし
            'setup_link_sent_at' => 'datetime',
            'password_set_at' => 'datetime',
            'suspended_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function proBowlerByLicense()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_license_no', 'license_no');
    }

    public function approvedBalls()
    {
        return $this->belongsToMany(
            ApprovedBall::class,
            'approved_ball_pro_bowler',
            'license_no',
            'approved_ball_id',
            'pro_bowler_license_no',
            'id'
        )->withPivot('year')->withTimestamps();
    }

    public function accountStatusLogs()
    {
        return $this->hasMany(UserAccountStatusLog::class)->latest('id');
    }

    public function isAccountActive(): bool
    {
        return ($this->account_status ?: self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function getAccountStatusLabelAttribute(): string
    {
        return match ($this->account_status ?: self::STATUS_ACTIVE) {
            self::STATUS_ACTIVE => '利用中',
            self::STATUS_SUSPENDED => '利用停止',
            self::STATUS_CLOSED => '終了',
            default => (string) $this->account_status,
        };
    }

    // === ロール判定 ===

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEditor(): bool
    {
        return $this->role === 'editor';
    }

    public function isMember(): bool
    {
        return in_array($this->role, ['member', 'bowler', null], true); // bowler/nullは旧データ対応
    }
}
