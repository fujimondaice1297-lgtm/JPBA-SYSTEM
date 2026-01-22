<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovedBall extends Model
{
    protected $table = 'approved_balls';

    protected $fillable = ['id','name','manufacturer','name_kana','approved','release_date'];
    protected $casts = [
        'approved'     => 'boolean',
        'release_date' => 'date', // ← これで $ball->release_date->format() が使える
    ];


    public function proBowlers()
    {
        return $this->belongsToMany(User::class, 'approved_ball_pro_bowler', 'approved_ball_id', 'pro_bowler_license_no', 'id')
            ->withPivot('year')
            ->withTimestamps();
    }
}
