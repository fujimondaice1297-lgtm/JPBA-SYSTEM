<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentFile extends Model
{
    protected $table = 'tournament_files';

    // type: outline_public / outline_player / oil_pattern / custom
    protected $fillable = [
        'tournament_id', 'type', 'title', 'file_path', 'visibility', 'sort_order',
    ];

    public $timestamps = false;

    // visibility（可視性）: public / members
    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
