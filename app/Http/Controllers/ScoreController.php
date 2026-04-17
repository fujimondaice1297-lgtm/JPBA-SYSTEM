<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GameScore;
use App\Models\ProBowler;
use App\Models\Tournament;
use App\Services\ScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ScoreController extends Controller
{
    public function input(Request $r)
    {
        $tournaments = Tournament::orderBy('start_date', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'name']);

        $new = (array) session('stage_settings', []);
        $old = (array) session('score_settings', []);

        $stageSettingsMap = [];

        foreach ($new as $tid => $st) {
            $tid = (string) $tid;
            foreach ((array) $st as $label => $g) {
                $g = (int) $g;
                if ($g > 0) {
                    $stageSettingsMap[$tid][$label] = $g;
                }
            }
        }

        foreach ($old as $tid => $st) {
            $tid = (string) $tid;
            $pre   = (int) ($st['prelim'] ?? 0);
            $semi  = (int) ($st['semi'] ?? 0);
            $final = (int) ($st['final'] ?? 0);

            if ($pre > 0) {
                $stageSettingsMap[$tid]['予選'] = $stageSettingsMap[$tid]['予選'] ?? $pre;
            }
            if ($semi > 0) {
                $stageSettingsMap[$tid]['準決勝'] = $stageSettingsMap[$tid]['準決勝'] ?? $semi;
            }
            if ($final > 0) {
                $stageSettingsMap[$tid]['決勝'] = $stageSettingsMap[$tid]['決勝'] ?? $final;
            }
        }

        foreach ($stageSettingsMap as $tid => $st) {
            $clean = [];
            foreach (['予選', '準々決勝', '準決勝', '決勝'] as $label) {
                $g = (int) ($st[$label] ?? 0);
                if ($g > 0) {
                    $clean[$label] = $g;
                }
            }
            $stageSettingsMap[$tid] = $clean;
        }

        $playerLookupRows = ProBowler::query()
            ->select(['license_no', 'name_kanji'])
            ->whereNotNull('license_no')
            ->whereNotNull('name_kanji')
            ->orderBy('license_no')
            ->get();

        $entryPlayerMap = [];
        $tournamentIds = $tournaments->pluck('id')->all();

        if (!empty($tournamentIds)) {
            $entryRows = DB::table('tournament_entries as te')
                ->join('pro_bowlers as pb', 'pb.id', '=', 'te.pro_bowler_id')
                ->whereIn('te.tournament_id', $tournamentIds)
                ->where('te.status', 'entry')
                ->select([
                    'te.tournament_id',
                    'pb.license_no',
                    'pb.name_kanji',
                ])
                ->orderBy('te.tournament_id')
                ->orderBy('pb.license_no')
                ->get();

            foreach ($entryRows as $row) {
                $licenseNo = trim((string) ($row->license_no ?? ''));
                $nameKanji = trim((string) ($row->name_kanji ?? ''));
                if ($licenseNo === '' || $nameKanji === '') {
                    continue;
                }

                $digits = $this->normalizeDigits($licenseNo);
                if ($digits === '') {
                    continue;
                }

                $last4 = strlen($digits) >= 4
                    ? substr($digits, -4)
                    : str_pad($digits, 4, '0', STR_PAD_LEFT);

                $gender = preg_match('/^[MF]/i', $licenseNo)
                    ? strtoupper(substr($licenseNo, 0, 1))
                    : '';

                $tid = (string) $row->tournament_id;
                $entryPlayerMap[$tid] = $entryPlayerMap[$tid] ?? [];

                $entryPlayerMap[$tid][] = [
                    'license_no'    => $licenseNo,
                    'license_digits'=> $digits,
                    'license_last4' => $last4,
                    'name_kanji'    => $nameKanji,
                    'gender'        => $gender,
                ];
            }

            foreach ($entryPlayerMap as $tid => $rows) {
                $uniq = [];
                foreach ($rows as $row) {
                    $key = $row['license_no'] . '|' . $row['name_kanji'];
                    $uniq[$key] = $row;
                }
                $entryPlayerMap[$tid] = array_values($uniq);
            }
        }

        return view('scores.input', compact(
            'tournaments',
            'stageSettingsMap',
            'playerLookupRows',
            'entryPlayerMap'
        ));
    }

    public function saveSettingBulk(Request $r)
    {
        $r->validate([
            'tournament_id' => 'required|integer',
        ]);

        $tid = (int) $r->tournament_id;

        $mapJA = [];
        $stages = (array) $r->input('stages', []);
        foreach ($stages as $label => $row) {
            $g = (int) ($row['total_games'] ?? 0);
            $enabled = (isset($row['enabled']) && (string) $row['enabled'] === '1') || $g > 0;
            if ($enabled && $g > 0) {
                $mapJA[(string) $label] = $g;
            }
        }

        $p = (int) $r->input('prelim_games', 0);
        $s = (int) $r->input('semi_games', 0);
        $f = (int) $r->input('final_games', 0);

        if ($p > 0) {
            $mapJA['予選'] = $mapJA['予選'] ?? $p;
        }
        if ($s > 0) {
            $mapJA['準決勝'] = $mapJA['準決勝'] ?? $s;
        }
        if ($f > 0) {
            $mapJA['決勝'] = $mapJA['決勝'] ?? $f;
        }

        $allNew = (array) session('stage_settings', []);
        $allNew[$tid] = $mapJA;
        session()->put('stage_settings', $allNew);

        $allOld = (array) session('score_settings', []);
        $allOld[$tid] = [
            'prelim' => (int) ($mapJA['予選'] ?? 0),
            'semi'   => (int) ($mapJA['準決勝'] ?? 0),
            'final'  => (int) ($mapJA['決勝'] ?? 0),
        ];
        session()->put('score_settings', $allOld);

        return back()->with('ok', 'ステージ設定を保存しました');
    }

    public function saveSetting(Request $r)
    {
        return $this->saveSettingBulk($r);
    }

    public function store(Request $r)
    {
        $r->validate([
            'tournament_id'   => 'required|integer',
            'stage'           => 'required|string',
            'game_number'     => 'required|integer|min:1|max:30',
            'identifier_type' => 'required|string|in:license_number,entry_number,name',
            'gender'          => 'nullable|in:M,F',
            'rows'            => 'required|array',
        ]);

        $tid    = (int) $r->tournament_id;
        $stage  = (string) $r->stage;
        $game   = (int) $r->game_number;
        $type   = (string) $r->identifier_type;
        $gender = $r->gender ?: null;
        $shift  = trim((string) $r->input('shift', ''));

        $historyPayload = $this->normalizeHistoryPayload(
            $this->buildHistoryPayload($tid, $stage, $game, $shift, $gender, $type),
            $type
        );

        if ($game > 1) {
            foreach ($r->rows as $i => $row) {
                $rawId = trim((string) ($row['id'] ?? ''));
                $score = (int) ($row['score'] ?? 0);

                if ($rawId === '' || $score <= 0) {
                    continue;
                }

                $normalized = $this->normalizeIdentifierInput($rawId, $type);

                if ($type === 'license_number' && in_array($normalized, $historyPayload['ambiguousKeys'], true)) {
                    return back()
                        ->withErrors(['rows.' . $i . '.id' => '同じ4桁番号の候補が複数あります。性別を指定してください。'])
                        ->withInput();
                }

                if ($normalized === '' || !in_array($normalized, $historyPayload['prevKeys'], true)) {
                    return back()
                        ->withErrors(['rows.' . $i . '.id' => '前ゲームまでに存在しない識別値です（大会・ステージ・シフト・性別も確認）'])
                        ->withInput();
                }
            }
        }

        $seen = [];
        foreach ($r->rows as $i => $row) {
            $rawId = trim((string) ($row['id'] ?? ''));
            if ($rawId === '') {
                continue;
            }

            $normalized = $this->normalizeIdentifierInput($rawId, $type);
            $dupKey = ($gender ?? '') . '#' . $shift . '#' . $normalized;

            if (isset($seen[$dupKey])) {
                return back()
                    ->withErrors(['rows.' . $i . '.id' => '同一ゲーム内で重複しています'])
                    ->withInput();
            }

            $seen[$dupKey] = true;
        }

        $count = 0;

        foreach ($r->rows as $row) {
            $rawId = trim((string) ($row['id'] ?? ''));
            $score = (int) ($row['score'] ?? 0);

            if ($rawId === '' || $score <= 0) {
                continue;
            }

            $resolvedBowler = $this->resolveBowlerFromInput($rawId, $type, $gender);
            $existing = $this->findExistingScore($tid, $stage, $game, $shift, $gender, $rawId, $type, $resolvedBowler);

            if ($existing) {
                $existing->score = $score;
                if ($gender && !$existing->gender) {
                    $existing->gender = $gender;
                }
                if ($shift !== '' && !$existing->shift) {
                    $existing->shift = $shift;
                }
                if ($resolvedBowler) {
                    if (!$existing->license_number && !empty($resolvedBowler['license_no'])) {
                        $existing->license_number = $resolvedBowler['license_no'];
                    }
                    if (!$existing->name && !empty($resolvedBowler['name_kanji'])) {
                        $existing->name = $resolvedBowler['name_kanji'];
                    }
                }
                $existing->save();
                $count++;
                continue;
            }

            $data = [
                'tournament_id' => $tid,
                'stage'         => $stage,
                'game_number'   => $game,
                'score'         => $score,
                'gender'        => $gender,
                'shift'         => $shift !== '' ? $shift : null,
            ];

            if ($type === 'license_number') {
                $data['license_number'] = $rawId;
            } elseif ($type === 'entry_number') {
                $data['entry_number'] = $rawId;
            } else {
                $data['name'] = $rawId;
            }

            if ($resolvedBowler) {
                if (empty($data['license_number']) && !empty($resolvedBowler['license_no'])) {
                    $data['license_number'] = $resolvedBowler['license_no'];
                }
                if (empty($data['name']) && !empty($resolvedBowler['name_kanji'])) {
                    $data['name'] = $resolvedBowler['name_kanji'];
                }
            }

            GameScore::create($data);
            $count++;
        }

        return back()
            ->with('success', "{$count} 件のスコアを登録しました")
            ->withInput($r->only(['tournament_id', 'stage', 'game_number', 'identifier_type', 'gender', 'shift']));
    }

    public function result(Request $r, ScoreService $service)
    {
        $opt = [
            'tournament_id'   => (int) $r->get('tournament_id'),
            'stage'           => (string) $r->get('stage', '予選'),
            'upto_game'       => (int) $r->get('upto_game', 1),
            'shifts'          => (string) $r->get('shifts', ''),
            'gender_filter'   => (string) $r->get('gender_filter', ''),
            'per_point'       => (int) $r->get('per_point', 200),
            'border_type'     => (string) $r->get('border_type', 'rank'),
            'border_value'    => $r->get('border_value'),
            'carry_prelim'    => (int) $r->get('carry_prelim', 0),
            'carry_semifinal' => (int) $r->get('carry_semifinal', 0),
        ];

        $data = $service->getRankings($opt);

        $t = Tournament::find($opt['tournament_id']);
        $tournament_name = $t?->name ?? '大会名';

        return view('scores.result', [
            'rankings'        => $data['rows'],
            'meta'            => $data['meta'],
            'tournament_name' => $tournament_name,
            'stage'           => $opt['stage'],
            'upto_game'       => (int) $opt['upto_game'],
            'border_type'     => $opt['border_type'],
            'border_value'    => $opt['border_value'],
            'per_point'       => (int) $opt['per_point'],
            'carry_prelim'    => (int) $opt['carry_prelim'],
            'carry_semifinal' => (int) $opt['carry_semifinal'],
            'shifts'          => (string) $opt['shifts'],
            'gender_filter'   => (string) $opt['gender_filter'],
        ]);
    }

    public function board(Request $r, ScoreService $service)
    {
        return $this->result($r, $service);
    }

    public function apiExistingIds(Request $r)
    {
        $tid    = (int) $r->query('tournament_id');
        $stage  = (string) $r->query('stage', '予選');
        $game   = max(1, (int) $r->query('game_number', 1));
        $shift  = trim((string) $r->query('shift', ''));
        $gender = trim((string) $r->query('gender', ''));
        $type   = (string) $r->query('identifier_type', 'license_number');

        return response()->json(
            $this->normalizeHistoryPayload(
                $this->buildHistoryPayload($tid, $stage, $game, $shift, $gender !== '' ? $gender : null, $type),
                $type
            )
        );
    }

    public function updateOne(Request $r)
    {
        $r->validate([
            'tournament_id'   => 'required|integer',
            'stage'           => 'required|string',
            'game_number'     => 'required|integer|min:1|max:30',
            'identifier_type' => 'required|string|in:license_number,entry_number,name',
            'identifier'      => 'required|string',
            'score'           => 'required|integer|min:0|max:300',
            'shift'           => 'nullable|string',
            'gender'          => 'nullable|in:M,F',
        ]);

        $q = GameScore::query()
            ->where('tournament_id', (int) $r->tournament_id)
            ->where('stage', (string) $r->stage)
            ->where('game_number', (int) $r->game_number);

        if ($r->filled('shift')) {
            $q->where('shift', $r->shift);
        }
        if ($r->filled('gender')) {
            $q->where(function ($w) use ($r) {
                $w->where('gender', $r->gender)
                    ->orWhereNull('gender')
                    ->orWhere('gender', '');
            });
        }

        $col = (string) $r->identifier_type;
        $val = trim((string) $r->identifier);

        if ($col === 'license_number') {
            $digits = $this->normalizeDigits($val);
            $q->where('license_number', 'like', '%' . $digits);
        } elseif ($col === 'name') {
            $q->where('name', $this->normalizeName($val));
        } else {
            $q->where('entry_number', $val);
        }

        $row = $q->first();
        if ($row) {
            $row->score = (int) $r->score;
            if ($r->filled('gender') && !$row->gender) {
                $row->gender = $r->gender;
            }
            $row->save();

            return back()->with('success', '1件更新しました');
        }

        return back()->with('success', '対象データが見つかりません（何も更新していません）');
    }

    public function deleteOne(Request $r)
    {
        $r->validate([
            'tournament_id'   => 'required|integer',
            'stage'           => 'required|string',
            'game_number'     => 'required|integer|min:1|max:30',
            'identifier_type' => 'required|string|in:license_number,entry_number,name',
            'identifier'      => 'required|string',
            'shift'           => 'nullable|string',
            'gender'          => 'nullable|in:M,F',
        ]);

        $q = GameScore::query()
            ->where('tournament_id', (int) $r->tournament_id)
            ->where('stage', (string) $r->stage)
            ->where('game_number', (int) $r->game_number);

        if ($r->filled('shift')) {
            $q->where('shift', $r->shift);
        }
        if ($r->filled('gender')) {
            $q->where(function ($w) use ($r) {
                $w->where('gender', $r->gender)
                    ->orWhereNull('gender')
                    ->orWhere('gender', '');
            });
        }

        $col = (string) $r->identifier_type;
        $val = trim((string) $r->identifier);

        if ($col === 'license_number') {
            $digits = $this->normalizeDigits($val);
            $q->where('license_number', 'like', '%' . $digits);
        } elseif ($col === 'name') {
            $q->where('name', $this->normalizeName($val));
        } else {
            $q->where('entry_number', $val);
        }

        $deleted = $q->delete();
        return back()->with('success', "{$deleted} 件を削除しました");
    }

    public function clearGame(Request $r)
    {
        $r->validate([
            'tournament_id' => 'required|integer',
            'stage'         => 'required|string',
            'game_number'   => 'required|integer|min:1|max:30',
            'shift'         => 'nullable|string',
            'gender'        => 'nullable|in:M,F',
        ]);

        $q = GameScore::query()
            ->where('tournament_id', (int) $r->tournament_id)
            ->where('stage', (string) $r->stage)
            ->where('game_number', (int) $r->game_number);

        if ($r->filled('shift')) {
            $q->where('shift', $r->shift);
        }
        if ($r->filled('gender')) {
            $q->where(function ($w) use ($r) {
                $w->where('gender', $r->gender)
                    ->orWhereNull('gender')
                    ->orWhere('gender', '');
            });
        }

        $deleted = $q->delete();
        return back()->with('success', "{$deleted} 件を削除しました（このゲーム）");
    }

    public function clearAll(Request $r)
    {
        $r->validate([
            'tournament_id' => 'required|integer',
            'stage'         => 'required|string',
            'shift'         => 'nullable|string',
            'gender'        => 'nullable|in:M,F',
        ]);

        $q = GameScore::query()
            ->where('tournament_id', (int) $r->tournament_id)
            ->where('stage', (string) $r->stage);

        if ($r->filled('shift')) {
            $q->where('shift', $r->shift);
        }
        if ($r->filled('gender')) {
            $q->where(function ($w) use ($r) {
                $w->where('gender', $r->gender)
                    ->orWhereNull('gender')
                    ->orWhere('gender', '');
            });
        }

        $deleted = $q->delete();
        return back()->with('success', "{$deleted} 件を削除しました（ステージ全体）");
    }

    private function buildHistoryPayload(int $tid, string $stage, int $game, string $shift, ?string $gender, string $type): array
    {
        $base = GameScore::query()
            ->where('tournament_id', $tid)
            ->where('stage', $stage);

        if ($shift !== '') {
            $base->where('shift', $shift);
        }

        if ($gender) {
            $base->where(function ($q) use ($gender) {
                $q->where('gender', $gender)
                    ->orWhereNull('gender')
                    ->orWhere('gender', '')
                    ->orWhere(function ($q2) use ($gender) {
                        $q2->whereNotNull('license_number')
                            ->where('license_number', 'like', $gender . '%');
                    });
            });
        }

        $prevRows = (clone $base)
            ->where('game_number', '<', $game)
            ->orderBy('game_number')
            ->get(['license_number', 'entry_number', 'name', 'game_number', 'score', 'gender']);

        $existingRows = (clone $base)
            ->where('game_number', $game)
            ->get(['license_number', 'entry_number', 'name', 'gender']);

        $maps = $this->buildBowlerMapsFromRows($prevRows->concat($existingRows), $gender);

        if ($type === 'license_number') {
            $historyMap = [];
            $prevKeys = [];
            $existsThisGame = [];
            $ambiguousKeys = [];

            $prevGrouped = [];
            foreach ($prevRows as $row) {
                $digitsKey = $this->resolveRowKey($row, 'license_number', $maps, $gender);
                if ($digitsKey === '') {
                    continue;
                }

                if (!isset($prevGrouped[$digitsKey])) {
                    $prevGrouped[$digitsKey] = [
                        'scores'   => [],
                        'total'    => 0,
                        'variants' => [],
                    ];
                }

                $variant = $this->licenseVariantKey((string) ($row->license_number ?? ''), (string) ($row->gender ?? ''));
                $prevGrouped[$digitsKey]['variants'][$variant] = true;

                $g = (int) $row->game_number;
                $s = (int) $row->score;

                if (!isset($prevGrouped[$digitsKey]['scores'][$g])) {
                    $prevGrouped[$digitsKey]['scores'][$g] = 0;
                }

                $prevGrouped[$digitsKey]['scores'][$g] += $s;
                $prevGrouped[$digitsKey]['total'] += $s;
            }

            foreach ($prevGrouped as $digitsKey => $entry) {
                $realVariants = array_values(array_filter(array_keys($entry['variants']), fn ($v) => $v !== 'UNK'));
                $realVariants = array_values(array_unique($realVariants));

                if (!$gender && count($realVariants) > 1) {
                    $ambiguousKeys[] = $digitsKey;
                    continue;
                }

                ksort($entry['scores']);
                $prevKeys[] = $digitsKey;
                $historyMap[$digitsKey] = [
                    'scores' => $entry['scores'],
                    'total'  => $entry['total'],
                ];
            }

            $existGrouped = [];
            foreach ($existingRows as $row) {
                $digitsKey = $this->resolveRowKey($row, 'license_number', $maps, $gender);
                if ($digitsKey === '') {
                    continue;
                }

                if (!isset($existGrouped[$digitsKey])) {
                    $existGrouped[$digitsKey] = ['variants' => []];
                }

                $variant = $this->licenseVariantKey((string) ($row->license_number ?? ''), (string) ($row->gender ?? ''));
                $existGrouped[$digitsKey]['variants'][$variant] = true;
            }

            foreach ($existGrouped as $digitsKey => $entry) {
                $realVariants = array_values(array_filter(array_keys($entry['variants']), fn ($v) => $v !== 'UNK'));
                $realVariants = array_values(array_unique($realVariants));

                if (!$gender && count($realVariants) > 1) {
                    $ambiguousKeys[] = $digitsKey;
                    continue;
                }

                $existsThisGame[] = $digitsKey;
            }

            return [
                'prevKeys'         => array_values(array_unique($prevKeys)),
                'prevDigits'       => array_values(array_unique($prevKeys)),
                'existsThisGame'   => array_values(array_unique($existsThisGame)),
                'historyMap'       => $historyMap,
                'ambiguousKeys'    => array_values(array_unique($ambiguousKeys)),
                'enforceFirstGame' => ($game > 1),
            ];
        }

        $prevKeysMap = [];
        $historyMap = [];

        foreach ($prevRows as $row) {
            $key = $this->resolveRowKey($row, $type, $maps, $gender);
            if ($key === '') {
                continue;
            }

            $prevKeysMap[$key] = true;

            if (!isset($historyMap[$key])) {
                $historyMap[$key] = [
                    'scores' => [],
                    'total'  => 0,
                ];
            }

            $g = (int) $row->game_number;
            $s = (int) $row->score;

            if (!isset($historyMap[$key]['scores'][$g])) {
                $historyMap[$key]['scores'][$g] = 0;
            }

            $historyMap[$key]['scores'][$g] += $s;
            $historyMap[$key]['total'] += $s;
        }

        $existsThisGameMap = [];
        foreach ($existingRows as $row) {
            $key = $this->resolveRowKey($row, $type, $maps, $gender);
            if ($key !== '') {
                $existsThisGameMap[$key] = true;
            }
        }

        return [
            'prevKeys'         => array_values(array_keys($prevKeysMap)),
            'prevDigits'       => [],
            'existsThisGame'   => array_values(array_keys($existsThisGameMap)),
            'historyMap'       => $historyMap,
            'ambiguousKeys'    => [],
            'enforceFirstGame' => ($game > 1),
        ];
    }

    private function buildBowlerMapsFromRows(Collection $rows, ?string $gender): array
    {
        $licenseDigits = [];
        $names = [];

        foreach ($rows as $row) {
            $digits = $this->normalizeDigits((string) ($row->license_number ?? ''));
            if ($digits !== '') {
                $licenseDigits[$digits] = true;
            }

            $name = $this->normalizeName((string) ($row->name ?? ''));
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        $byDigits = [];
        $byName = [];

        $query = ProBowler::query()->select(['license_no', 'name_kanji']);

        $query->where(function ($q) use ($licenseDigits, $names) {
            foreach (array_keys($licenseDigits) as $digits) {
                $q->orWhere('license_no', 'like', '%' . $digits);
            }
            if (!empty($names)) {
                $q->orWhereIn('name_kanji', array_keys($names));
            }
        });

        $bowlers = $query->get();

        foreach ($bowlers as $bowler) {
            $licenseNo = trim((string) ($bowler->license_no ?? ''));
            $nameKanji = $this->normalizeName((string) ($bowler->name_kanji ?? ''));
            if ($licenseNo === '' || $nameKanji === '') {
                continue;
            }

            $digits = $this->normalizeDigits($licenseNo);
            if ($digits === '') {
                continue;
            }

            $sex = preg_match('/^[MF]/i', $licenseNo) ? strtoupper(substr($licenseNo, 0, 1)) : '';

            $payload = [
                'license_no' => $licenseNo,
                'digits'     => $digits,
                'name_kanji' => $nameKanji,
                'sex'        => $sex,
            ];

            $byDigits[$digits] = $byDigits[$digits] ?? [];
            $byDigits[$digits][] = $payload;

            $byName[$nameKanji] = $byName[$nameKanji] ?? [];
            $byName[$nameKanji][] = $payload;
        }

        if ($gender) {
            foreach ($byDigits as $k => $items) {
                $filtered = array_values(array_filter($items, function ($item) use ($gender) {
                    return ($item['sex'] ?? '') === $gender || ($item['sex'] ?? '') === '';
                }));
                $byDigits[$k] = !empty($filtered) ? $filtered : $items;
            }
            foreach ($byName as $k => $items) {
                $filtered = array_values(array_filter($items, function ($item) use ($gender) {
                    return ($item['sex'] ?? '') === $gender || ($item['sex'] ?? '') === '';
                }));
                $byName[$k] = !empty($filtered) ? $filtered : $items;
            }
        }

        return [
            'byDigits' => $byDigits,
            'byName'   => $byName,
        ];
    }

    private function resolveRowKey(object $row, string $identifierType, array $maps, ?string $gender): string
    {
        if ($identifierType === 'entry_number') {
            return trim((string) ($row->entry_number ?? ''));
        }

        if ($identifierType === 'license_number') {
            $direct = $this->normalizeDigits((string) ($row->license_number ?? ''));
            if ($direct !== '') {
                return $direct;
            }

            $name = $this->normalizeName((string) ($row->name ?? ''));
            if ($name === '') {
                return '';
            }

            $cands = $maps['byName'][$name] ?? [];
            $digits = array_values(array_unique(array_map(fn ($x) => (string) ($x['digits'] ?? ''), $cands)));
            $digits = array_values(array_filter($digits));
            return count($digits) === 1 ? $digits[0] : '';
        }

        if ($identifierType === 'name') {
            $direct = $this->normalizeName((string) ($row->name ?? ''));
            if ($direct !== '') {
                return $direct;
            }

            $digits = $this->normalizeDigits((string) ($row->license_number ?? ''));
            if ($digits === '') {
                return '';
            }

            $cands = $maps['byDigits'][$digits] ?? [];
            $names = array_values(array_unique(array_map(fn ($x) => (string) ($x['name_kanji'] ?? ''), $cands)));
            $names = array_values(array_filter($names));
            return count($names) === 1 ? $names[0] : '';
        }

        return '';
    }


    private function normalizeHistoryPayload(array $payload, string $identifierType): array
    {
        $historyMap = [];

        foreach ((array) ($payload['historyMap'] ?? []) as $rawKey => $entry) {
            $normalizedKey = $this->normalizeIdentifierInput((string) $rawKey, $identifierType);
            if ($normalizedKey === '') {
                continue;
            }

            $historyMap[$normalizedKey] = [
                'scores' => (array) ($entry['scores'] ?? []),
                'total'  => (int) ($entry['total'] ?? 0),
            ];
        }

        $prevKeys = $this->normalizeIdentifierValueList(
            array_merge(
                array_keys($historyMap),
                (array) ($payload['prevKeys'] ?? []),
                (array) ($payload['prevDigits'] ?? [])
            ),
            $identifierType
        );

        $existsThisGame = $this->normalizeIdentifierValueList(
            (array) ($payload['existsThisGame'] ?? []),
            $identifierType
        );

        $ambiguousKeys = $this->normalizeIdentifierValueList(
            (array) ($payload['ambiguousKeys'] ?? []),
            $identifierType
        );

        return [
            'prevKeys'         => $prevKeys,
            'prevDigits'       => $identifierType === 'license_number' ? $prevKeys : [],
            'existsThisGame'   => $existsThisGame,
            'historyMap'       => $historyMap,
            'ambiguousKeys'    => $ambiguousKeys,
            'enforceFirstGame' => (bool) ($payload['enforceFirstGame'] ?? false),
        ];
    }

    private function normalizeIdentifierValueList(array $values, string $identifierType): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $key = $this->normalizeIdentifierInput((string) $value, $identifierType);
            if ($key === '') {
                continue;
            }

            $normalized[(string) $key] = true;
        }

        return array_values(array_map(static fn ($key) => (string) $key, array_keys($normalized)));
    }

    private function resolveBowlerFromInput(string $value, string $type, ?string $gender): ?array
    {
        if ($type === 'entry_number') {
            return null;
        }

        if ($type === 'license_number') {
            $digits = $this->normalizeDigits($value);
            if ($digits === '') {
                return null;
            }

            $rows = ProBowler::query()
                ->select(['license_no', 'name_kanji'])
                ->where('license_no', 'like', '%' . $digits)
                ->get();

            if ($gender) {
                $filtered = $rows->filter(function ($row) use ($gender) {
                    $licenseNo = trim((string) ($row->license_no ?? ''));
                    return preg_match('/^[MF]/i', $licenseNo)
                        ? strtoupper(substr($licenseNo, 0, 1)) === $gender
                        : true;
                })->values();

                if ($filtered->count() === 1) {
                    $row = $filtered->first();
                    return [
                        'license_no' => (string) ($row->license_no ?? ''),
                        'name_kanji' => (string) ($row->name_kanji ?? ''),
                    ];
                }
            }

            if ($rows->count() === 1) {
                $row = $rows->first();
                return [
                    'license_no' => (string) ($row->license_no ?? ''),
                    'name_kanji' => (string) ($row->name_kanji ?? ''),
                ];
            }

            return null;
        }

        $name = $this->normalizeName($value);
        if ($name === '') {
            return null;
        }

        $rows = ProBowler::query()
            ->select(['license_no', 'name_kanji'])
            ->where('name_kanji', $name)
            ->get();

        if ($gender) {
            $filtered = $rows->filter(function ($row) use ($gender) {
                $licenseNo = trim((string) ($row->license_no ?? ''));
                return preg_match('/^[MF]/i', $licenseNo)
                    ? strtoupper(substr($licenseNo, 0, 1)) === $gender
                    : true;
            })->values();

            if ($filtered->count() === 1) {
                $row = $filtered->first();
                return [
                    'license_no' => (string) ($row->license_no ?? ''),
                    'name_kanji' => (string) ($row->name_kanji ?? ''),
                ];
            }
        }

        if ($rows->count() === 1) {
            $row = $rows->first();
            return [
                'license_no' => (string) ($row->license_no ?? ''),
                'name_kanji' => (string) ($row->name_kanji ?? ''),
            ];
        }

        return null;
    }

    private function findExistingScore(
        int $tid,
        string $stage,
        int $game,
        string $shift,
        ?string $gender,
        string $rawId,
        string $type,
        ?array $resolvedBowler
    ): ?GameScore {
        $q = GameScore::query()
            ->where('tournament_id', $tid)
            ->where('stage', $stage)
            ->where('game_number', $game);

        if ($shift !== '') {
            $q->where('shift', $shift);
        }

        if ($gender) {
            $q->where(function ($w) use ($gender) {
                $w->where('gender', $gender)
                    ->orWhereNull('gender')
                    ->orWhere('gender', '');
            });
        }

        $normalizedName = $this->normalizeName($rawId);
        $normalizedDigits = $this->normalizeDigits($rawId);

        $q->where(function ($w) use ($type, $rawId, $normalizedName, $normalizedDigits, $resolvedBowler) {
            if ($type === 'license_number' && $normalizedDigits !== '') {
                $w->orWhere('license_number', 'like', '%' . $normalizedDigits);
            }

            if ($type === 'name' && $normalizedName !== '') {
                $w->orWhere('name', $normalizedName);
            }

            if ($type === 'entry_number') {
                $w->orWhere('entry_number', $rawId);
            }

            if ($resolvedBowler) {
                $resolvedLicenseDigits = $this->normalizeDigits((string) ($resolvedBowler['license_no'] ?? ''));
                $resolvedName = $this->normalizeName((string) ($resolvedBowler['name_kanji'] ?? ''));

                if ($resolvedLicenseDigits !== '') {
                    $w->orWhere('license_number', 'like', '%' . $resolvedLicenseDigits);
                }
                if ($resolvedName !== '') {
                    $w->orWhere('name', $resolvedName);
                }
            }
        });

        return $q->first();
    }

    private function normalizeIdentifierInput(string $value, string $identifierType): string
    {
        if ($identifierType === 'license_number') {
            return $this->normalizeDigits($value);
        }

        if ($identifierType === 'entry_number') {
            return trim($value);
        }

        return $this->normalizeName($value);
    }

    private function licenseVariantKey(string $licenseNo, string $gender): string
    {
        $licenseNo = trim($licenseNo);
        $digits = $this->normalizeDigits($licenseNo);
        if ($digits === '') {
            return 'UNK';
        }

        if (preg_match('/^[MF]/i', $licenseNo)) {
            return strtoupper(substr($licenseNo, 0, 1)) . '-' . $digits;
        }

        if ($gender !== '' && in_array(strtoupper($gender), ['M', 'F'], true)) {
            return strtoupper($gender) . '-' . $digits;
        }

        return 'UNK';
    }

    private function normalizeDigits(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        $digits = ltrim((string) $digits, '0');
        return $digits === '' ? '' : $digits;
    }

    private function normalizeName(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }
}