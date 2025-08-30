<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovedBall extends Model
{
    protected $table = 'approved_balls';

    protected $fillable = [
        'release_year',
        'manufacturer',
        'name',
        'name_kana',
        'approved', // ← これが抜けてると保存されない
    ];

    public function proBowlers()
    {
        return $this->belongsToMany(User::class, 'approved_ball_pro_bowler', 'approved_ball_id', 'pro_bowler_license_no', 'id')
            ->withPivot('year')
            ->withTimestamps();
    }
}
