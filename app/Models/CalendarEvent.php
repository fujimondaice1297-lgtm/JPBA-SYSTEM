<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $fillable = ['title','start_date','end_date','venue','kind'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    // カラーとラベル（薄い紫）
    public function getColorClassAttribute(): string { return 'bg-manual'; }

    public function getKindLabelAttribute(): string
    {
        return match($this->kind){
            'pro_test' => 'プロテスト',
            'approved' => '承認大会',
            default    => 'その他',
        };
    }
}
