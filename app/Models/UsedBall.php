<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

    class UsedBall extends Model
    {
        protected $fillable = [
            'pro_bowler_id', // ← OK
            'approved_ball_id',
            'serial_number',
            'inspection_number',
            'registered_at',
            'expires_at',
        ];

        public function proBowler()
        {
            return $this->belongsTo(\App\Models\ProBowler::class, 'pro_bowler_id');
        }

        public function approvedBall()
        {
            return $this->belongsTo(ApprovedBall::class);
        }

        public function entryLinks() {
            return $this->hasMany(\App\Models\TournamentEntryBall::class, 'used_ball_id');
        }

        public function bowler()
        {
            return $this->belongsTo(\App\Models\ProBowler::class, 'pro_bowler_id');
        }

        public function tournamentEntries()
        {
            return $this->belongsToMany(
                \App\Models\TournamentEntry::class,
                'tournament_entry_balls',
                'used_ball_id',
                'tournament_entry_id'
            )->withTimestamps();
        }

        // （任意）日付 cast：画面で扱いやすくなる
        protected $casts = [
            'registered_at' => 'date',
            'expires_at'    => 'date',
        ];

    }

