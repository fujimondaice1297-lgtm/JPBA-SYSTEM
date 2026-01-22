<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMailout extends Model
{
    protected $fillable = [
        'group_id','sender_user_id','subject','body',
        'from_address','from_name','status','sent_count','fail_count'
    ];

    public function group(){ return $this->belongsTo(Group::class); }
    public function sender(){ return $this->belongsTo(User::class,'sender_user_id'); }
    public function recipients(){ return $this->hasMany(GroupMailRecipient::class,'mailout_id'); }
}
