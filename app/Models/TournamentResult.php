<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentResult extends Model
{
    protected $table = 'tournament_results';

    protected $fillable = [
        'pro_bowler_license_no',
        'tournament_id',
        'ranking',
        'points',
        'total_pin',
        'games',
        'average',
        'prize_money',
        'ranking_year',
        // ある環境では ID で持っている可能性があるため許可しておく（無ければ無視される）
        'pro_bowler_id',
    ];

    /** 大会 */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * 選手（ID 参照版）
     * 旧データで pro_bowler_id を使っている画面互換のため
     */
    public function bowler()
    {
        // カラムが無い環境でも eager load 時は空配列になり安全にスルーされる
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id', 'id');
    }

    /**
     * 選手（ライセンス番号参照版）
     * 現行の登録で使っている関係
     */
    public function player()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_license_no', 'license_no');
    }
}
