<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DrawController extends Controller
{
    /** 管理者: 設定画面（簡易） */
    public function settings(Tournament $tournament)
    {
        $this->authorizeAdmin();
        return view('tournaments.draw_settings', compact('tournament'));
    }

    /** 管理者: 設定保存 */
    public function saveSettings(Request $request, Tournament $tournament)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'shift_codes'        => ['nullable','string'], // 例 "A,B,C"
            'shift_draw_open_at' => ['nullable','date'],
            'shift_draw_close_at'=> ['nullable','date','after_or_equal:shift_draw_open_at'],
            'lane_draw_open_at'  => ['nullable','date'],
            'lane_draw_close_at' => ['nullable','date','after_or_equal:lane_draw_open_at'],
            'lane_from'          => ['nullable','integer','min:1'],
            'lane_to'            => ['nullable','integer','gte:lane_from','max:999'],
        ]);

        $tournament->update($data);

        return back()->with('success','抽選設定を保存しました。');
    }

    /** 会員: シフト抽選（HTML遷移） */
    public function shift(TournamentEntry $entry)
    {
        $this->authorizeEntryOwner($entry);
        $res = $this->performShiftDraw($entry);

        return back()->with($res['ok'] ? 'success' : 'error', $res['msg']);
    }

    /** 会員: レーン抽選（HTML遷移） */
    public function lane(TournamentEntry $entry)
    {
        $this->authorizeEntryOwner($entry);
        $res = $this->performLaneDraw($entry);

        return back()->with($res['ok'] ? 'success' : 'error', $res['msg']);
    }

    /** API: シフト抽選（JSON） */
    public function shiftApi(TournamentEntry $entry)
    {
        $this->authorizeEntryOwner($entry);
        $res = $this->performShiftDraw($entry);
        return response()->json($res, $res['ok'] ? 200 : 400);
    }

    /** API: レーン抽選（JSON） */
    public function laneApi(TournamentEntry $entry)
    {
        $this->authorizeEntryOwner($entry);
        $res = $this->performLaneDraw($entry);
        return response()->json($res, $res['ok'] ? 200 : 400);
    }

    // ---------------- 内部実装 ----------------

    protected function authorizeAdmin(): void
    {
        if (!(Auth::user()->is_admin ?? false)) {
            abort(403);
        }
    }

    protected function authorizeEntryOwner(TournamentEntry $entry): void
    {
        if (Auth::user()->pro_bowler_id !== $entry->pro_bowler_id) {
            abort(403);
        }
        if ($entry->status !== 'entry') {
            abort(400, 'エントリー有効時のみ抽選できます。');
        }
    }

    /** シフト抽選本体 */
    protected function performShiftDraw(TournamentEntry $entry): array
    {
        $t = $entry->tournament()->first();

        // 受付期間チェック（設定があれば）
        if ($t->shift_draw_open_at && now()->lt($t->shift_draw_open_at)) {
            return ['ok'=>false,'msg'=>'シフト抽選の受付前です。'];
        }
        if ($t->shift_draw_close_at && now()->gt($t->shift_draw_close_at)) {
            return ['ok'=>false,'msg'=>'シフト抽選の受付を終了しました。'];
        }

        if ($entry->shift) {
            return ['ok'=>false,'msg'=>'すでにシフトが確定しています。'];
        }

        // 候補シフト
        $codes = collect(($t->shift_codes ? explode(',', $t->shift_codes) : []))
            ->map(fn($s)=>trim($s))
            ->filter()
            ->values();

        if ($codes->isEmpty()) {
            // シフト制でない大会は「A」固定などでもOK。ここはAを採用。
            $codes = collect(['A']);
        }

        // バランス抽選：最も割付が少ないシフト群からランダム選択
        $counts = DB::table('tournament_entries')
            ->select('shift', DB::raw('count(*) as c'))
            ->where('tournament_id', $t->id)
            ->whereIn('shift', $codes->all())
            ->groupBy('shift')
            ->pluck('c','shift');

        $min = null;
        $candidates = [];
        foreach ($codes as $code) {
            $val = (int)($counts[$code] ?? 0);
            if ($min === null || $val < $min) {
                $min = $val;
                $candidates = [$code];
            } elseif ($val === $min) {
                $candidates[] = $code;
            }
        }
        $chosen = $candidates[array_rand($candidates)];

        // 確定
        $entry->update(['shift' => $chosen]);

        return ['ok'=>true,'msg'=>"シフト「{$chosen}」が確定しました。",'shift'=>$chosen];
    }

    /** レーン抽選本体 */
    protected function performLaneDraw(TournamentEntry $entry): array
    {
        $t = $entry->tournament()->first();

        // 受付期間チェック（設定があれば）
        if ($t->lane_draw_open_at && now()->lt($t->lane_draw_open_at)) {
            return ['ok'=>false,'msg'=>'レーン抽選の受付前です。'];
        }
        if ($t->lane_draw_close_at && now()->gt($t->lane_draw_close_at)) {
            return ['ok'=>false,'msg'=>'レーン抽選の受付を終了しました。'];
        }

        if (!$entry->shift) {
            return ['ok'=>false,'msg'=>'先にシフトを確定してください。'];
        }
        if ($entry->lane) {
            return ['ok'=>false,'msg'=>'すでにレーンが確定しています。'];
        }
        if (!$t->lane_from || !$t->lane_to || $t->lane_from > $t->lane_to) {
            return ['ok'=>false,'msg'=>'大会のレーン範囲が未設定です。（管理者に連絡してください）'];
        }

        $from = (int)$t->lane_from;
        $to   = (int)$t->lane_to;

        // 同シフトの使用済みレーンを取得
        $used = DB::table('tournament_entries')
            ->where('tournament_id', $t->id)
            ->where('shift', $entry->shift)
            ->whereNotNull('lane')
            ->pluck('lane')
            ->map(fn($v)=> (int)$v)
            ->all();

        $candidates = [];
        for ($i=$from; $i<=$to; $i++) {
            if (!in_array($i, $used, true)) {
                $candidates[] = $i;
            }
        }
        if (empty($candidates)) {
            return ['ok'=>false,'msg'=>'空きレーンがありません。'];
        }

        // ランダムに1つ選ぶ（衝突対策：更新時に二重使用を再チェック）
        shuffle($candidates);
        $chosen = $candidates[0];

        // 競合対策：トランザクションで確定前に最新使用状況を再確認
        DB::transaction(function () use ($t, $entry, $chosen) {
            $exists = DB::table('tournament_entries')
                ->where('tournament_id', $t->id)
                ->where('shift', $entry->shift)
                ->where('lane', $chosen)
                ->exists();

            if ($exists) {
                // まれに衝突したら例外でロールバックして上位でメッセージ
                throw new \RuntimeException('選択中にレーンが埋まりました。もう一度お試しください。');
            }
            $entry->update(['lane' => $chosen]);
        });

        return ['ok'=>true,'msg'=>"レーン「{$chosen}」が確定しました。",'lane'=>$chosen];
    }
}
