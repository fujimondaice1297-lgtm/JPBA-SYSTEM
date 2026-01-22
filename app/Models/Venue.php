<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $table = 'venues';

    protected $fillable = [
        'name',          // 会場名
        'address',       // 住所
        'postal_code',   // 郵便番号（例: 101-0047）
        'tel',           // 電話
        'fax',           // FAX
        'website_url',   // 公式サイトURL
        'note',          // 会場データ（旧: 備考）
    ];

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
}
