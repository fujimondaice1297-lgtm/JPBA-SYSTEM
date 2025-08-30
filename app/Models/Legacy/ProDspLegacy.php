<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class ProDspLegacy extends Model
{
    // ← ここが重要！「mysql_legacy」に変更
    protected $connection = 'mysql_legacy';

    protected $table = 'pro_dsp';    // 旧テーブル名
    protected $primaryKey = 'id';    // 主キー名（問題なし）
    public $timestamps = false;      // 日付が無いならfalse（正しい）
}
