<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DrawController extends Controller
{
    public function settings(Tournament $tournament)
    {
        $this->authorizeAdmin();

        return view('tournaments.draw_settings', compact('tournament'));
    }

    public function saveSettings(Request $request, Tournament $tournament)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'use_shift_draw' => ['nullable', 'boolean'],
            'shift_codes' => ['nullable', 'string', 'max:255'],
            'accept_shift_preference' => ['nullable', 'boolean'],
            'shift_draw_open_at' => ['nullable', 'date'],
            'shift_draw_close_at' => ['nullable', 'date', 'after_or_equal:shift_draw_open_at'],

            'use_lane_draw' => ['nullable', 'boolean'],
            'lane_draw_open_at' => ['nullable', 'date'],
            'lane_draw_close_at' => ['nullable', 'date', 'after_or_equal:lane_draw_open_at'],
            'lane_from' => ['nullable', 'integer', 'min:1'],
            'lane_to' => ['nullable', 'integer', 'gte:lane_from', 'max:999'],
            'lane_assignment_mode' => ['nullable', 'in:single_lane,box'],
            'box_player_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'odd_lane_player_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'even_lane_player_count' => ['nullable', 'integer', 'min:1', 'max:12'],

            'shift_auto_draw_reminder_enabled' => ['nullable', 'boolean'],
            'shift_auto_draw_reminder_send_on' => ['nullable', 'date'],
            'lane_auto_draw_reminder_enabled' => ['nullable', 'boolean'],
            'lane_auto_draw_reminder_send_on' => ['nullable', 'date'],
        ]);

        $normalized = [
            'use_shift_draw' => $request->boolean('use_shift_draw'),
            'use_lane_draw' => $request->boolean('use_lane_draw'),
            'accept_shift_preference' => $request->boolean('use_shift_draw') && $request->boolean('accept_shift_preference'),
            'shift_codes' => $this->normalizeShiftCodes((string) ($data['shift_codes'] ?? '')),
            'shift_draw_open_at' => $request->filled('shift_draw_open_at') ? Carbon::parse($request->input('shift_draw_open_at')) : null,
            'shift_draw_close_at' => $request->filled('shift_draw_close_at') ? Carbon::parse($request->input('shift_draw_close_at')) : null,
            'lane_draw_open_at' => $request->filled('lane_draw_open_at') ? Carbon::parse($request->input('lane_draw_open_at')) : null,
            'lane_draw_close_at' => $request->filled('lane_draw_close_at') ? Carbon::parse($request->input('lane_draw_close_at')) : null,
            'lane_from' => $data['lane_from'] ?? null,
            'lane_to' => $data['lane_to'] ?? null,
            'lane_assignment_mode' => $data['lane_assignment_mode'] ?? 'single_lane',
            'box_player_count' => $data['box_player_count'] ?? null,
            'odd_lane_player_count' => $data['odd_lane_player_count'] ?? null,
            'even_lane_player_count' => $data['even_lane_player_count'] ?? null,

            'shift_auto_draw_reminder_enabled' => $request->boolean('shift_auto_draw_reminder_enabled'),
            'shift_auto_draw_reminder_send_on' => $request->filled('shift_auto_draw_reminder_send_on')
                ? Carbon::parse($request->input('shift_auto_draw_reminder_send_on'))->toDateString()
                : null,
            'lane_auto_draw_reminder_enabled' => $request->boolean('lane_auto_draw_reminder_enabled'),
            'lane_auto_draw_reminder_send_on' => $request->filled('lane_auto_draw_reminder_send_on')
                ? Carbon::parse($request->input('lane_auto_draw_reminder_send_on'))->toDateString()
                : null,

            // 旧一括設定は互換保持のみ。新運用では使わない。
            'auto_draw_reminder_enabled' => false,
            'auto_draw_reminder_days_before' => 7,
            'auto_draw_reminder_pending_type' => 'either',
        ];

        if ($normalized['use_shift_draw'] && blank($normalized['shift_codes'])) {
            throw ValidationException::withMessages([
                'shift_codes' => 'シフト抽選を使う場合は、シフト候補を1つ以上入力してください。',
            ]);
        }

        if ($normalized['use_lane_draw']) {
            if (is_null($normalized['lane_from']) || is_null($normalized['lane_to'])) {
                throw ValidationException::withMessages([
                    'lane_from' => 'レーン抽選を使う場合は、使用レーン範囲を入力してください。',
                ]);
            }

            if ($normalized['lane_assignment_mode'] === 'box') {
                if (
                    is_null($normalized['box_player_count']) ||
                    is_null($normalized['odd_lane_player_count']) ||
                    is_null($normalized['even_lane_player_count'])
                ) {
                    throw ValidationException::withMessages([
                        'box_player_count' => 'BOX運用を使う場合は、BOX人数と奇数/偶数レーン人数を入力してください。',
                    ]);
                }

                if (((int) $normalized['odd_lane_player_count'] + (int) $normalized['even_lane_player_count']) !== (int) $normalized['box_player_count']) {
                    throw ValidationException::withMessages([
                        'box_player_count' => 'BOX人数は「奇数レーン人数 + 偶数レーン人数」と一致させてください。',
                    ]);
                }
            }
        }

        if ($normalized['shift_auto_draw_reminder_enabled']) {
            if (!$normalized['use_shift_draw']) {
                throw ValidationException::withMessages([
                    'shift_auto_draw_reminder_enabled' => 'シフト未抽選DMを使う場合は、シフト抽選を有効にしてください。',
                ]);
            }

            if (empty($normalized['shift_auto_draw_reminder_send_on'])) {
                throw ValidationException::withMessages([
                    'shift_auto_draw_reminder_send_on' => 'シフト未抽選DMを使う場合は、送信日を入力してください。',
                ]);
            }

            if (empty($normalized['shift_draw_close_at'])) {
                throw ValidationException::withMessages([
                    'shift_draw_close_at' => 'シフト未抽選DMを使う場合は、シフト抽選終了日時を入力してください。',
                ]);
            }

            $sendOn = Carbon::parse($normalized['shift_auto_draw_reminder_send_on'])->startOfDay();
            $deadline = Carbon::parse($normalized['shift_draw_close_at'])->endOfDay();

            if ($sendOn->gt($deadline)) {
                throw ValidationException::withMessages([
                    'shift_auto_draw_reminder_send_on' => 'シフト未抽選DMの送信日は、シフト抽選終了日以前にしてください。',
                ]);
            }
        }

        if ($normalized['lane_auto_draw_reminder_enabled']) {
            if (!$normalized['use_lane_draw']) {
                throw ValidationException::withMessages([
                    'lane_auto_draw_reminder_enabled' => 'レーン未抽選DMを使う場合は、レーン抽選を有効にしてください。',
                ]);
            }

            if (empty($normalized['lane_auto_draw_reminder_send_on'])) {
                throw ValidationException::withMessages([
                    'lane_auto_draw_reminder_send_on' => 'レーン未抽選DMを使う場合は、送信日を入力してください。',
                ]);
            }

            if (empty($normalized['lane_draw_close_at'])) {
                throw ValidationException::withMessages([
                    'lane_draw_close_at' => 'レーン未抽選DMを使う場合は、レーン抽選終了日時を入力してください。',
                ]);
            }

            $sendOn = Carbon::parse($normalized['lane_auto_draw_reminder_send_on'])->startOfDay();
            $deadline = Carbon::parse($normalized['lane_draw_close_at'])->endOfDay();

            if ($sendOn->gt($deadline)) {
                throw ValidationException::withMessages([
                    'lane_auto_draw_reminder_send_on' => 'レーン未抽選DMの送信日は、レーン抽選終了日以前にしてください。',
                ]);
            }
        }

        $tournament->update($normalized);

        return back()->with('success', '運営 / 抽選設定を保存しました。');
    }

    public function shift(TournamentEntry $entry)
    {
        $this->authorizeEntryOwner($entry);
        $res = $this->performShiftDraw($entry);

        return back()->with($res['ok'] ? 'success' : 'error', $res['msg']);
    }

    public function lane(TournamentEntry $entry)
    {
        $this->authorizeEntryOwner($entry);
        $res = $this->performLaneDraw($entry);

        return back()->with($res['ok'] ? 'success' : 'error', $res['msg']);
    }

    public function shiftApi(TournamentEntry $entry)
    {
        $this->authorizeEntryOwner($entry);
        $res = $this->performShiftDraw($entry);

        return response()->json($res, $res['ok'] ? 200 : 400);
    }

    public function laneApi(TournamentEntry $entry)
    {
        $this->authorizeEntryOwner($entry);
        $res = $this->performLaneDraw($entry);

        return response()->json($res, $res['ok'] ? 200 : 400);
    }

    public function bulk(Request $request, Tournament $tournament)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'target' => ['required', 'in:all,shift,lane'],
        ]);

        $target = (string) $data['target'];

        if (!(bool) ($tournament->use_shift_draw ?? false) && !(bool) ($tournament->use_lane_draw ?? false)) {
            return redirect()
                ->route('tournaments.draws.index', $tournament->id)
                ->with('error', 'この大会は抽選運用が設定されていません。');
        }

        if ($target === 'shift' && !(bool) ($tournament->use_shift_draw ?? false)) {
            return redirect()
                ->route('tournaments.draws.index', $tournament->id)
                ->with('error', 'この大会はシフト抽選を使いません。');
        }

        if ($target === 'lane' && !(bool) ($tournament->use_lane_draw ?? false)) {
            return redirect()
                ->route('tournaments.draws.index', $tournament->id)
                ->with('error', 'この大会はレーン抽選を使いません。');
        }

        $entries = $this->pendingEntriesQuery($tournament, $target)
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return redirect()
                ->route('tournaments.draws.index', ['tournament' => $tournament->id, 'pending_draw' => 1])
                ->with('success', '対象の未抽選者はいません。');
        }

        $summary = [
            'total' => $entries->count(),
            'shift_success' => 0,
            'shift_failed' => 0,
            'lane_success' => 0,
            'lane_failed' => 0,
        ];

        $errors = [];

        foreach ($entries as $entry) {
            $bowlerName = trim((string) ($entry->bowler?->name_kanji ?? ''));
            $licenseNo = trim((string) ($entry->bowler?->license_no ?? ''));

            if (
                in_array($target, ['all', 'shift'], true) &&
                (bool) ($tournament->use_shift_draw ?? false) &&
                blank($entry->shift)
            ) {
                $res = $this->performShiftDraw($entry, true);

                if ($res['ok']) {
                    $summary['shift_success']++;
                } else {
                    $summary['shift_failed']++;
                    $errors[] = '[' . $licenseNo . ' ' . $bowlerName . '] シフト: ' . $res['msg'];
                }

                $entry->refresh();
            }

            if (
                in_array($target, ['all', 'lane'], true) &&
                (bool) ($tournament->use_lane_draw ?? false) &&
                blank($entry->lane)
            ) {
                if ((bool) ($tournament->use_shift_draw ?? false) && blank($entry->shift)) {
                    $summary['lane_failed']++;
                    $errors[] = '[' . $licenseNo . ' ' . $bowlerName . '] レーン: 先にシフト抽選が必要です。';
                    continue;
                }

                $res = $this->performLaneDraw($entry, true);

                if ($res['ok']) {
                    $summary['lane_success']++;
                } else {
                    $summary['lane_failed']++;
                    $errors[] = '[' . $licenseNo . ' ' . $bowlerName . '] レーン: ' . $res['msg'];
                }
            }
        }

        $messages = ['一括抽選を実行しました。対象 ' . $summary['total'] . ' 件'];

        if ((bool) ($tournament->use_shift_draw ?? false) && in_array($target, ['all', 'shift'], true)) {
            $messages[] = 'シフト成功 ' . $summary['shift_success'] . ' 件';
            if ($summary['shift_failed'] > 0) {
                $messages[] = 'シフト失敗 ' . $summary['shift_failed'] . ' 件';
            }
        }

        if ((bool) ($tournament->use_lane_draw ?? false) && in_array($target, ['all', 'lane'], true)) {
            $messages[] = 'レーン成功 ' . $summary['lane_success'] . ' 件';
            if ($summary['lane_failed'] > 0) {
                $messages[] = 'レーン失敗 ' . $summary['lane_failed'] . ' 件';
            }
        }

        $redirect = redirect()->route('tournaments.draws.index', [
            'tournament' => $tournament->id,
            'pending_draw' => 1,
        ]);

        $redirect->with('success', implode(' / ', $messages));

        if (!empty($errors)) {
            $preview = array_slice($errors, 0, 5);
            $errorMessage = implode(' | ', $preview);

            if (count($errors) > 5) {
                $errorMessage .= ' | ほか ' . (count($errors) - 5) . ' 件';
            }

            $redirect->with('error', $errorMessage);
        }

        return $redirect;
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

    protected function performShiftDraw(TournamentEntry $entry, bool $force = false): array
    {
        $t = $entry->tournament()->first();

        if (!$t?->use_shift_draw) {
            return ['ok' => false, 'msg' => 'この大会はシフト抽選を行いません。'];
        }

        if (!$force) {
            if ($t->shift_draw_open_at && now()->lt($t->shift_draw_open_at)) {
                return ['ok' => false, 'msg' => 'シフト抽選の受付前です。'];
            }
            if ($t->shift_draw_close_at && now()->gt($t->shift_draw_close_at)) {
                return ['ok' => false, 'msg' => 'シフト抽選の受付を終了しました。'];
            }
        }

        if ($entry->shift) {
            return ['ok' => false, 'msg' => 'すでにシフトが確定しています。'];
        }

        $codes = $this->shiftCodeCollection($t->shift_codes);
        if ($codes->isEmpty()) {
            return ['ok' => false, 'msg' => 'シフト候補が未設定です。'];
        }

        $counts = DB::table('tournament_entries')
            ->select('shift', DB::raw('count(*) as c'))
            ->where('tournament_id', $t->id)
            ->where('status', 'entry')
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

        $preferred = trim((string) ($entry->preferred_shift_code ?? ''));
        if ($preferred !== '' && in_array($preferred, $candidates, true)) {
            $chosen = $preferred;
        } else {
            $chosen = $candidates[array_rand($candidates)];
        }

        $entry->update([
            'shift' => $chosen,
            'shift_drawn' => true,
        ]);

        return ['ok' => true, 'msg' => "シフト「{$chosen}」が確定しました。", 'shift' => $chosen];
    }

    protected function performLaneDraw(TournamentEntry $entry, bool $force = false): array
    {
        $t = $entry->tournament()->first();

        if (!$t?->use_lane_draw) {
            return ['ok' => false, 'msg' => 'この大会はレーン抽選を行いません。'];
        }

        if (!$force) {
            if ($t->lane_draw_open_at && now()->lt($t->lane_draw_open_at)) {
                return ['ok' => false, 'msg' => 'レーン抽選の受付前です。'];
            }
            if ($t->lane_draw_close_at && now()->gt($t->lane_draw_close_at)) {
                return ['ok' => false, 'msg' => 'レーン抽選の受付を終了しました。'];
            }
        }

        if ($t->use_shift_draw && !$entry->shift) {
            return ['ok' => false, 'msg' => '先にシフトを確定してください。'];
        }

        if ($entry->lane) {
            return ['ok' => false, 'msg' => 'すでにレーンが確定しています。'];
        }

        if (!$t->lane_from || !$t->lane_to || $t->lane_from > $t->lane_to) {
            return ['ok' => false, 'msg' => '大会のレーン範囲が未設定です。（管理者に連絡してください）'];
        }

        $mode = (string) ($t->lane_assignment_mode ?? 'single_lane');
        $from = (int) $t->lane_from;
        $to = (int) $t->lane_to;

        $usedQuery = DB::table('tournament_entries')
            ->select('lane', DB::raw('count(*) as c'))
            ->where('tournament_id', $t->id)
            ->where('status', 'entry')
            ->whereNotNull('lane');

        if ($t->use_shift_draw) {
            $usedQuery->where('shift', $entry->shift);
        }

        $usedCounts = $usedQuery
            ->groupBy('lane')
            ->pluck('c', 'lane');

        $candidateRows = [];

        for ($lane = $from; $lane <= $to; $lane++) {
            $used = (int) ($usedCounts[$lane] ?? 0);
            $capacity = 1;

            if ($mode === 'box') {
                $odd = (int) ($t->odd_lane_player_count ?? 0);
                $even = (int) ($t->even_lane_player_count ?? 0);

                if ($odd < 1 || $even < 1 || ((int) ($t->box_player_count ?? 0) !== ($odd + $even))) {
                    return ['ok' => false, 'msg' => 'BOX運用設定が不正です。（奇数/偶数レーン人数とBOX人数を確認してください）'];
                }

                $capacity = ($lane % 2 === 1) ? $odd : $even;
            }

            if ($used >= $capacity) {
                continue;
            }

            $fillRatio = $capacity > 0 ? ($used / $capacity) : 999;

            $candidateRows[] = [
                'lane' => $lane,
                'used' => $used,
                'capacity' => $capacity,
                'fill_ratio' => $fillRatio,
            ];
        }

        if (empty($candidateRows)) {
            return ['ok' => false, 'msg' => '空きレーンがありません。'];
        }

        usort($candidateRows, function (array $a, array $b) {
            if ($a['fill_ratio'] === $b['fill_ratio']) {
                if ($a['used'] === $b['used']) {
                    return $a['lane'] <=> $b['lane'];
                }

                return $a['used'] <=> $b['used'];
            }

            return $a['fill_ratio'] <=> $b['fill_ratio'];
        });

        $bestRatio = $candidateRows[0]['fill_ratio'];
        $bestUsed = $candidateRows[0]['used'];

        $candidates = array_values(array_filter($candidateRows, function (array $row) use ($bestRatio, $bestUsed) {
            return $row['fill_ratio'] === $bestRatio && $row['used'] === $bestUsed;
        }));

        $picked = $candidates[array_rand($candidates)];
        $chosenLane = (int) $picked['lane'];
        $chosenCapacity = (int) $picked['capacity'];

        try {
            DB::transaction(function () use ($t, $entry, $chosenLane, $chosenCapacity) {
                $check = DB::table('tournament_entries')
                    ->where('tournament_id', $t->id)
                    ->where('status', 'entry')
                    ->where('lane', $chosenLane);

                if ($t->use_shift_draw) {
                    $check->where('shift', $entry->shift);
                }

                $currentCount = (int) $check->count();

                if ($currentCount >= $chosenCapacity) {
                    throw new \RuntimeException('選択中にレーンが埋まりました。もう一度お試しください。');
                }

                $entry->update([
                    'lane' => $chosenLane,
                    'lane_drawn' => true,
                ]);
            });
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }

        return ['ok' => true, 'msg' => "レーン「{$chosenLane}」が確定しました。", 'lane' => $chosenLane];
    }

    private function pendingEntriesQuery(Tournament $tournament, string $target): Builder
    {
        $query = TournamentEntry::query()
            ->with('bowler')
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry');

        if ($target === 'shift') {
            return $query->whereNull('shift');
        }

        if ($target === 'lane') {
            return $query->whereNull('lane');
        }

        return $query->where(function (Builder $builder) use ($tournament) {
            $applied = false;

            if ((bool) ($tournament->use_shift_draw ?? false)) {
                $builder->whereNull('shift');
                $applied = true;
            }

            if ((bool) ($tournament->use_lane_draw ?? false)) {
                if ($applied) {
                    $builder->orWhereNull('lane');
                } else {
                    $builder->whereNull('lane');
                    $applied = true;
                }
            }

            if (!$applied) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    private function shiftCodeCollection(?string $shiftCodes)
    {
        return collect(explode(',', (string) $shiftCodes))
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizeShiftCodes(string $shiftCodes): ?string
    {
        $normalized = $this->shiftCodeCollection($shiftCodes)->implode(',');

        return $normalized !== '' ? $normalized : null;
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