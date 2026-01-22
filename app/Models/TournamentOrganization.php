<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentOrganization extends Model
{
    protected $table = 'tournament_organizations';

    // 一括代入（mass assignment）で受け取る属性
    protected $fillable = [
        'tournament_id',   // 大会ID（リレーション saveMany() でも明示許可）
        'category',        // host / special_sponsor / sponsor / support / cooperation
        'name',            // 名称
        'url',             // URL
        'sort_order',      // 並び順
    ];

    public $timestamps = false; // マイグレーションでtimestamps無しなら必須

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
