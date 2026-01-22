<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Models\ProBowler;
use App\Models\ApprovedBall;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',                 // ← 過去の名残として残す場合のみ
        'role',                     // ← 新ロール管理ここで追加
        'pro_bowler_id',
        'pro_bowler_license_no',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean', // ← これは残しても動作には問題なし
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
        return in_array($this->role, ['member', null]); // nullは旧データ対応
    }
}
