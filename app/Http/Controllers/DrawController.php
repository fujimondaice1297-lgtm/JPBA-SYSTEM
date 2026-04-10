<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
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
            'shift_codes'         => ['nullable', 'string'],
            'shift_draw_open_at'  => ['nullable', 'date'],
            'shift_draw_close_at' => ['nullable', 'date', 'after_or_equal:shift_draw_open_at'],
            'lane_draw_open_at'   => ['nullable', 'date'],
            'lane_draw_close_at'  => ['nullable', 'date', 'after_or_equal:lane_draw_open_at'],
            'lane_from'           => ['nullable', 'integer', 'min:1'],
            'lane_to'             => ['nullable', 'integer', 'gte:lane_from', 'max:999'],
        ]);

        $tournament->update($data);

        return back()->with('success', '抽選設定を保存しました。');
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

    protected function authorizeAdmin(): void
    {
        $user = Auth::user();
        $isAdmin = $user && (method_exists($user, 'isAdmin') ? $user->isAdmin() : (bool) ($user->is_admin ?? false));

        if (!$isAdmin) {
            abort(403);
        }
    }

    protected function authorizeEntryOwner(TournamentEntry $entry): void
    {
        $user = Auth::user();
        $userProBowlerId = (int) ($user?->pro_bowler_id ?? 0);

        if ($userProBowlerId <= 0 || $userProBowlerId !== (int) $entry->pro_bowler_id) {
            abort(403, '自分のエントリー以外は操作できません。');
        }

        $bowler = ProBowler::query()->find($entry->pro_bowler_id);
        $eligibility = $this->resolveEntryEligibility($bowler);

        if (!$eligibility['allowed']) {
            abort(403, $eligibility['message']);
        }

        if ($entry->status !== 'entry') {
            abort(400, 'エントリー有効時のみ抽選できます。');
        }
    }

    /** シフト抽選本体 */
    protected function performShiftDraw(TournamentEntry $entry): array
    {
        $t = $entry->tournament()->first();

        if ($t->shift_draw_open_at && now()->lt($t->shift_draw_open_at)) {
            return ['ok' => false, 'msg' => 'シフト抽選の受付前です。'];
        }
        if ($t->shift_draw_close_at && now()->gt($t->shift_draw_close_at)) {
            return ['ok' => false, 'msg' => 'シフト抽選の受付を終了しました。'];
        }

        if ($entry->shift) {
            return ['ok' => false, 'msg' => 'すでにシフトが確定しています。'];
        }

        $codes = collect(($t->shift_codes ? explode(',', $t->shift_codes) : []))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();

        if ($codes->isEmpty()) {
            $codes = collect(['A']);
        }

        $counts = DB::table('tournament_entries')
            ->select('shift', DB::raw('count(*) as c'))
            ->where('tournament_id', $t->id)
            ->whereIn('shift', $codes->all())
            ->groupBy('shift')
            ->pluck('c', 'shift');

        $min = null;
        $candidates = [];

        foreach ($codes as $code) {
            $val = (int) ($counts[$code] ?? 0);

            if ($min === null || $val < $min) {
                $min = $val;
                $candidates = [$code];
            } elseif ($val === $min) {
                $candidates[] = $code;
            }
        }

        $chosen = $candidates[array_rand($candidates)];

        $entry->update(['shift' => $chosen]);

        return ['ok' => true, 'msg' => "シフト「{$chosen}」が確定しました。", 'shift' => $chosen];
    }

    /** レーン抽選本体 */
    protected function performLaneDraw(TournamentEntry $entry): array
    {
        $t = $entry->tournament()->first();

        if ($t->lane_draw_open_at && now()->lt($t->lane_draw_open_at)) {
            return ['ok' => false, 'msg' => 'レーン抽選の受付前です。'];
        }
        if ($t->lane_draw_close_at && now()->gt($t->lane_draw_close_at)) {
            return ['ok' => false, 'msg' => 'レーン抽選の受付を終了しました。'];
        }

        if (!$entry->shift) {
            return ['ok' => false, 'msg' => '先にシフトを確定してください。'];
        }
        if ($entry->lane) {
            return ['ok' => false, 'msg' => 'すでにレーンが確定しています。'];
        }
        if (!$t->lane_from || !$t->lane_to || $t->lane_from > $t->lane_to) {
            return ['ok' => false, 'msg' => '大会のレーン範囲が未設定です。（管理者に連絡してください）'];
        }

        $from = (int) $t->lane_from;
        $to = (int) $t->lane_to;

        $used = DB::table('tournament_entries')
            ->where('tournament_id', $t->id)
            ->where('shift', $entry->shift)
            ->whereNotNull('lane')
            ->pluck('lane')
            ->map(fn ($v) => (int) $v)
            ->all();

        $candidates = [];
        for ($i = $from; $i <= $to; $i++) {
            if (!in_array($i, $used, true)) {
                $candidates[] = $i;
            }
        }

        if (empty($candidates)) {
            return ['ok' => false, 'msg' => '空きレーンがありません。'];
        }

        shuffle($candidates);
        $chosen = $candidates[0];

        try {
            DB::transaction(function () use ($t, $entry, $chosen) {
                $exists = DB::table('tournament_entries')
                    ->where('tournament_id', $t->id)
                    ->where('shift', $entry->shift)
                    ->where('lane', $chosen)
                    ->exists();

                if ($exists) {
                    throw new \RuntimeException('選択中にレーンが埋まりました。もう一度お試しください。');
                }

                $entry->update(['lane' => $chosen]);
            });
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }

        return ['ok' => true, 'msg' => "レーン「{$chosen}」が確定しました。", 'lane' => $chosen];
    }

    private function resolveEntryEligibility(?ProBowler $bowler): array
    {
        if (!$bowler) {
            return [
                'allowed' => false,
                'message' => '選手情報が未結線のため、大会エントリーを利用できません。管理者に確認してください。',
            ];
        }

        $memberClass = (string) ($bowler->member_class ?? '');
        $officialEntryAllowed = (bool) ($bowler->can_enter_official_tournament ?? false);
        $isActive = (bool) ($bowler->is_active ?? false);

        if (!$isActive) {
            return [
                'allowed' => false,
                'message' => '現在の会員状態が無効のため、大会エントリー対象外です。',
            ];
        }

        if ($memberClass !== 'player') {
            return [
                'allowed' => false,
                'message' => $this->memberClassLabel($memberClass) . 'のため、大会エントリー対象外です。',
            ];
        }

        if (!$officialEntryAllowed) {
            return [
                'allowed' => false,
                'message' => '現在の会員区分では公式戦出場対象外として登録されています。',
            ];
        }

        return [
            'allowed' => true,
            'message' => '大会エントリー可能です。',
        ];
    }

    private function memberClassLabel(?string $memberClass): string
    {
        return match ($memberClass) {
            'player' => '競技者',
            'pro_instructor' => 'プロインストラクター',
            'honorary_or_overseas' => '名誉プロ・海外プロ',
            'other' => 'その他',
            default => '-',
        };
    }
}