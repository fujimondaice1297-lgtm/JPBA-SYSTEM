<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Information extends Model
{
    protected $table = 'informations';

    protected $fillable = [
        'title',
        'body',
        'is_public',
        'category',
        'published_at',
        'starts_at',
        'ends_at',
        'audience',
        'required_training_id',
    ];

    protected $casts = [
        'is_public' => 'bool',
        'published_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    /** 公開期間内 */
    public function scopeActive(Builder $q): Builder
    {
        $now = now();
        return $q->where(function($w) use ($now){
                $w->whereNull('starts_at')->orWhere('starts_at','<=',$now);
            })->where(function($w) use ($now){
                $w->whereNull('ends_at')->orWhere('ends_at','>=',$now);
            });
    }

    /** 一般公開のみ */
    public function scopePublic(Builder $q): Builder
    {
        return $q->where('is_public', true);
    }

    public function proBowler(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProBowler::class, 'pro_bowler_id');
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Training::class, 'required_training_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(\App\Models\InformationFile::class, 'information_id');
    }

    /** ログインユーザ向け絞り込み（公開＋条件） */
    public function scopeForUser(Builder $q, ?\App\Models\User $user): Builder
    {
        // 常に一般公開は含める
        $q->where(function($w) use ($user){
            $w->where('is_public', true)
              ->orWhere(function($m) use ($user){
                  if (!$user) { $m->whereRaw('1=0'); return; } // 未ログインなら会員向けはゼロ

                  $isLeader = (bool) optional($user->proBowler)->is_district_leader;

                  $m->where('is_public', false)
                    ->where(function($c) use ($isLeader){
                        $c->where('audience','members');
                        if ($isLeader) { $c->orWhere('audience','district_leaders'); }
                        $c->orWhere('audience','needs_training');
                    });
              });
        });

        // 未受講者向け（pro_bowler_trainings に完了レコードが「無い」ものを表示）
        if ($user && $user->proBowler) {
            $pbId = $user->proBowler->id;
            $q->where(function($w) use ($pbId){
                $w->where('audience','!=','needs_training')
                  ->orWhereNotExists(function($sub) use ($pbId){
                      $sub->from('pro_bowler_trainings')
                          ->whereColumn('pro_bowler_trainings.training_id','informations.required_training_id')
                          ->where('pro_bowler_trainings.pro_bowler_id',$pbId);
                  });
            });
        }

        return $q;
    }
}
