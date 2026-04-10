<?php

namespace App\Http\Controllers;

use App\Models\TournamentEntry;
use App\Models\UsedBall;
use App\Models\RegisteredBall;
use App\Models\ProBowler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentEntryBallController extends Controller
{
    /**
     * 使用ボール選択画面（会員）
     * - 画面表示前に registered_balls -> used_balls を同期
     * - 有効期限内 もしくは 検量証待ち（expires_at NULL）を表示
     */
    public function edit(TournamentEntry $entry)
    {
        if ($guard = $this->guardEntryAccess($entry)) {
            return $guard;
        }

        $this->syncFromRegisteredBalls((int) $entry->pro_bowler_id);

        $usedBalls = UsedBall::with('approvedBall')
            ->where('pro_bowler_id', $entry->pro_bowler_id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->orderByDesc('registered_at')
            ->get();

        $linkedIds = $entry->balls()->pluck('used_balls.id')->all();
        $existingCount = count($linkedIds);
        $remaining = max(0, 12 - $existingCount);

        $inspectionRequired = (bool) ($entry->tournament->inspection_required ?? false);

        return view('member.entry_balls_edit', compact(
            'entry',
            'usedBalls',
            'linkedIds',
            'existingCount',
            'remaining',
            'inspectionRequired'
        ));
    }

    /**
     * 複数ボールをまとめて登録（追加のみ、合計12上限）
     */
    public function bulkStore(Request $request, TournamentEntry $entry)
    {
        if ($guard = $this->guardEntryAccess($entry)) {
            return $guard;
        }

        $data = $request->validate([
            'used_ball_ids'   => ['array'],
            'used_ball_ids.*' => ['integer', 'exists:used_balls,id'],
        ]);

        $targetIds = collect($data['used_ball_ids'] ?? [])->unique()->values();
        if ($targetIds->isEmpty()) {
            return back()->with('success', '追加はありませんでした。');
        }

        $already = $entry->balls()->pluck('used_balls.id')->all();
        $toAttach = $targetIds->reject(fn ($id) => in_array($id, $already, true))->values();

        $current = count($already);
        $newCount = $toAttach->count();
        if ($current + $newCount > 12) {
            return back()->withErrors([
                'used_ball_ids' => "1大会で登録できるボールは最大12個までです。（現在 {$current} 個、追加 {$newCount} 個）",
            ]);
        }

        foreach ($toAttach as $ballId) {
            $usedBall = UsedBall::findOrFail($ballId);

            if ((int) $usedBall->pro_bowler_id !== (int) $entry->pro_bowler_id) {
                return back()->withErrors([
                    'used_ball_ids' => "自分のボールのみ登録できます。（ID: {$ballId}）",
                ]);
            }

            if (!is_null($usedBall->expires_at) && $usedBall->expires_at->lt(now()->startOfDay())) {
                return back()->withErrors([
                    'used_ball_ids' => "有効期限切れのボールは登録できません。（SN: {$usedBall->serial_number}）",
                ]);
            }
        }

        foreach ($toAttach as $ballId) {
            $entry->balls()->attach($ballId);
        }

        return redirect()
            ->route('member.entries.balls.edit', $entry->id)
            ->with('success', $toAttach->count() . '個のボールを登録しました。');
    }

    /**
     * （保持）単発API：テスト用途
     */
    public function store(Request $request, TournamentEntry $entry)
    {
        if ($guard = $this->guardEntryAccess($entry)) {
            return $guard;
        }

        $data = $request->validate([
            'used_ball_id' => ['required', 'integer', 'exists:used_balls,id'],
        ]);

        $usedBall = UsedBall::findOrFail($data['used_ball_id']);

        if (Auth::check() && Auth::user()->pro_bowler_id) {
            if ((int) $usedBall->pro_bowler_id !== (int) Auth::user()->pro_bowler_id) {
                return back()->withErrors(['used_ball_id' => '自分のボールのみ登録できます。']);
            }
        }

        if ((int) $usedBall->pro_bowler_id !== (int) $entry->pro_bowler_id) {
            return back()->withErrors(['used_ball_id' => 'このエントリーの選手のボールではありません。']);
        }

        if (!is_null($usedBall->expires_at) && $usedBall->expires_at->lt(now()->startOfDay())) {
            return back()->withErrors(['used_ball_id' => '有効期限切れのボールは登録できません。']);
        }

        if (!$entry->balls()->where('used_ball_id', $usedBall->id)->exists()) {
            if ($entry->balls()->count() >= 12) {
                return back()->withErrors(['used_ball_id' => '1大会で登録できるボールは最大12個までです。']);
            }
            $entry->balls()->attach($usedBall->id);
        }

        return back()->with('success', 'ボールを紐付けました。');
    }

    /**
     * 解除（会員は禁止。管理者のみ想定）
     */
    public function destroy(TournamentEntry $entry, UsedBall $usedBall)
    {
        $user = auth()->user();
        $isAdmin = $user && (method_exists($user, 'isAdmin') ? $user->isAdmin() : (bool) ($user->is_admin ?? false));

        if (!$isAdmin) {
            abort(403, 'この操作は許可されていません。');
        }

        $entry->balls()->detach($usedBall->id);

        return back()->with('success', 'ボールの紐付けを解除しました。');
    }

    private function guardEntryAccess(TournamentEntry $entry)
    {
        $userProBowlerId = (int) (Auth::user()?->pro_bowler_id ?? 0);

        if ($userProBowlerId <= 0 || $userProBowlerId !== (int) $entry->pro_bowler_id) {
            abort(403, '自分のエントリー以外は操作できません。');
        }

        $bowler = ProBowler::query()->find($entry->pro_bowler_id);
        $eligibility = $this->resolveEntryEligibility($bowler);

        if (!$eligibility['allowed']) {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', $eligibility['message']);
        }

        if ($entry->status !== 'entry') {
            return redirect()
                ->route('tournament.entry.select')
                ->with('error', 'エントリー有効時のみ大会使用ボールを操作できます。');
        }

        return null;
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

    /**
     * registered_balls -> used_balls 同期
     * - RegisteredBall は license_no ベース
     * - UsedBall は pro_bowler_id を要求するので、対応する ProBowler を解決して保存
     * - serial_number が既に used にあればスキップ（大文字小文字は無視）
     * - expires_at は RegisteredBall 側のロジックに従う（NULL=仮登録OK）
     */
    private function syncFromRegisteredBalls(int $proBowlerId): void
    {
        $pro = ProBowler::find($proBowlerId);
        if (!$pro || empty($pro->license_no)) {
            return;
        }

        $registered = RegisteredBall::where('license_no', $pro->license_no)->get();
        if ($registered->isEmpty()) {
            return;
        }

        $existingSerials = UsedBall::where('pro_bowler_id', $pro->id)
            ->pluck('serial_number')
            ->map(fn ($v) => mb_strtoupper((string) $v))
            ->all();

        foreach ($registered as $rb) {
            $sn = mb_strtoupper((string) $rb->serial_number);
            if (in_array($sn, $existingSerials, true)) {
                continue;
            }

            UsedBall::create([
                'pro_bowler_id'     => $pro->id,
                'approved_ball_id'  => $rb->approved_ball_id,
                'serial_number'     => $rb->serial_number,
                'inspection_number' => $rb->inspection_number,
                'registered_at'     => $rb->registered_at,
                'expires_at'        => $rb->expires_at,
            ]);

            $existingSerials[] = $sn;
        }
    }
}