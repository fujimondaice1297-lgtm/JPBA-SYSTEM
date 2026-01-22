<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMailRecipient extends Model
{
    protected $fillable = [
        'mailout_id','pro_bowler_id','email','status','sent_at','error_message'
    ];

    protected $casts = ['sent_at'=>'datetime'];

    public function bowler(){ return $this->belongsTo(ProBowler::class,'pro_bowler_id'); }
    public function mailout(){ return $this->belongsTo(GroupMailout::class,'mailout_id'); }
}
