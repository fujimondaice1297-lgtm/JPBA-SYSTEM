<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Tournament, TournamentEntry, UsedBall, ProBowler, TournamentResult};

class MobileController extends Controller
{
    public function me(Request $r)
    {
        $u = $r->user();
        $b = $u->proBowler;
        return [
            'user'=>['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'role'=>$u->role],
            'bowler'=>$b ? [
                'id'=>$b->id,'license_no'=>$b->license_no,'name'=>$b->name_kanji,
                'district'=>$b->district?->label,'sex'=>$b->sex
            ] : null,
        ];
    }

    public function tournaments()
    {
        return Tournament::query()
            ->latest('start_date')
            ->limit(200)
            ->get(['id','name','year','gender','start_date','end_date','venue_name','entry_start','entry_end']);
    }

    public function createEntry(Request $r)
    {
        $u = $r->user();
        $bowler = $u->proBowler;
        abort_unless($bowler, 403, 'Bowler required');

        $data = $r->validate(['tournament_id'=>'required|integer|exists:tournaments,id']);
        $entry = TournamentEntry::firstOrCreate(
            ['tournament_id'=>$data['tournament_id'],'pro_bowler_id'=>$bowler->id],
            ['status'=>'applied','is_paid'=>false]
        );
        return ['entry_id'=>$entry->id,'status'=>$entry->status];
    }

    public function myEntries(Request $r)
    {
        $b = $r->user()->proBowler;
        if (!$b) return [];
        return $b->entries()->with('tournament:id,name,start_date,end_date,year')
            ->latest()->get(['id','tournament_id','status','is_paid','shift_drawn','lane_drawn']);
    }

    public function entryBalls(Request $r, TournamentEntry $entry)
    {
        $this->authorizeEntry($r, $entry);
        return $entry->balls()->get(['used_balls.id','used_balls.brand','used_balls.model','used_balls.serial']);
    }

    public function saveEntryBalls(Request $r, TournamentEntry $entry)
    {
        $this->authorizeEntry($r, $entry);
        $data = $r->validate(['ball_ids'=>'array','ball_ids.*'=>'integer|exists:used_balls,id']);
        $entry->balls()->sync($data['ball_ids'] ?? []);
        return ['ok'=>true,'count'=>$entry->balls()->count()];
    }

    public function usedBalls(Request $r)
    {
        $b = $r->user()->proBowler;
        if (!$b) return [];
        return UsedBall::where('pro_bowler_id',$b->id)->latest()->get(['id','brand','model','serial','notes']);
    }

    public function createUsedBall(Request $r)
    {
        $b = $r->user()->proBowler; abort_unless($b, 403);
        $data = $r->validate([
            'brand'=>'required|string|max:100',
            'model'=>'required|string|max:100',
            'serial'=>'nullable|string|max:100',
            'notes'=>'nullable|string|max:500',
        ]);
        $ball = UsedBall::create($data + ['pro_bowler_id'=>$b->id]);
        return $ball->only(['id','brand','model','serial','notes']);
    }

    public function results()
    {
        return TournamentResult::query()
            ->latest('id')->limit(200)
            ->get(['id','tournament_id','pro_bowler_id','rank','score_total']);
    }

    private function authorizeEntry(Request $r, TournamentEntry $entry): void
    {
        $b = $r->user()->proBowler;
        abort_unless($b && $entry->pro_bowler_id === $b->id, 403);
    }
}
