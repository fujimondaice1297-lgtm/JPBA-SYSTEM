<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $fillable = [
        'key','name','type','rule_json','retention','expires_at',
        'show_on_mypage','preset','action_mypage','action_email','action_postal',
    ];

    protected $casts = [
        'rule_json' => 'array',
        'expires_at'=> 'date',
        'show_on_mypage' => 'boolean',
        'action_mypage' => 'boolean',
        'action_email'  => 'boolean',
        'action_postal' => 'boolean',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(ProBowler::class, 'group_members', 'group_id', 'pro_bowler_id')
                    ->withPivot(['source','assigned_at','expires_at'])
                    ->withTimestamps();
    }

    public function scopeKey($q, string $key) { return $q->where('key',$key); }

    /* === 日本語ラベル（表示専用） === */
    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'rule' ? 'ルール' : 'スナップショット';
    }
    public function getRetentionLabelAttribute(): string
    {
        return match($this->retention){
            'forever' => '永続',
            'fye'     => '年度末まで',
            'until'   => '指定日まで',
            default   => $this->retention,
        };
    }
}
