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
    // ← 1行にまとめて正しい書式で
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',

        // 運用上あるなら両方入れておく（どちらを使うかは環境に合わせて）
        'pro_bowler_id',
        'pro_bowler_license_no',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
        ];
    }

    public function proBowler()
    {
        return $this->belongsTo(\App\Models\ProBowler::class, 'pro_bowler_id');
    }

    /**
     * （必要なら）ライセンス番号での紐付け版
     * users.pro_bowler_license_no → pro_bowlers.license_no
     */
    public function proBowlerByLicense()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_license_no', 'license_no');
    }

    /**
     * 承認ボール（中間テーブル経由）
     * ※ ピボットに license_no があり、それが users.pro_bowler_license_no と一致する想定の場合
     */
    public function approvedBalls()
    {
        return $this->belongsToMany(
            ApprovedBall::class,
            'approved_ball_pro_bowler', // pivot table
            'license_no',               // pivot: 自分側FK（ProBowlerのlicense_no想定）
            'approved_ball_id',         // pivot: 相手側FK
            'pro_bowler_license_no',    // users側のキー
            'id'                        // ApprovedBall側のキー
        )->withPivot('year')->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
