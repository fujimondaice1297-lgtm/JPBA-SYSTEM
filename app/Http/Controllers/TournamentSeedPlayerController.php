<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentSeedPlayer;
use App\Services\ProBowlerSeedService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TournamentSeedPlayerController extends Controller
{
    public function index(Tournament $tournament)
    {
        $seedPlayers = $this->activeSeedPlayers($tournament);
        $priorityPlayers = $this->buildTournamentPriorityPlayers($tournament, $seedPlayers);
        $annualSeedOverlapMap = $this->annualSeedOverlapMap($tournament, $seedPlayers);

        return view('tournament_seed_players.index', [
            'tournament' => $tournament,
            'seedPlayers' => $seedPlayers,
            'priorityPlayers' => $priorityPlayers,
            'seedSourceOptions' => $this->seedSourceOptions(),
            'annualSeedOverlapMap' => $annualSeedOverlapMap,
        ]);
    }

    public function pdf(Tournament $tournament)
    {
        $seedPlayers = $this->activeSeedPlayers($tournament);
        $priorityPlayers = $this->buildTournamentPriorityPlayers($tournament, $seedPlayers);

        $pdf = Pdf::loadView('tournament_seed_players.pdf', [
            'tournament' => $tournament,
            'priorityPlayers' => $priorityPlayers,
            'seedPlayers' => $seedPlayers,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($this->pdfFileName($tournament));
    }

    public function store(Request $request, Tournament $tournament, ProBowlerSeedService $seedService)
    {
        $validated = $request->validate([
            'seed_player_id' => ['nullable', 'integer'],
            'license_no' => ['required', 'string', 'max:50'],
            'seed_source_type' => ['required', 'string', Rule::in(array_keys($this->seedSourceOptions()))],
            'priority_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'display_label' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $inputLicenseNo = $this->normalizeLicenseNo($validated['license_no']);
        $tournamentGender = $this->normalizeGenderCode($tournament->gender ?? null);
        $inputGender = $this->genderFromLicenseNo($inputLicenseNo);

        if ($tournamentGender !== null && $inputGender !== null && $inputGender !== $tournamentGender) {
            return back()
                ->withErrors([
                    'license_no' => '入力されたライセンスNoの性別記号が、この大会の対象性別と一致しません。',
                ])
                ->withInput();
        }

        $bowler = $this->findBowlerByLicenseNo($inputLicenseNo, $tournament);
        $licenseNo = $this->canonicalLicenseNo($inputLicenseNo, $bowler);
        $seedPlayerId = !empty($validated['seed_player_id']) ? (int) $validated['seed_player_id'] : null;
        $duplicateSeedPlayer = $this->findDuplicateActiveSeedPlayer($tournament, $licenseNo, $bowler, $seedPlayerId);

        if ($duplicateSeedPlayer !== null) {
            return back()
                ->withErrors([
                    'license_no' => 'この選手はすでに大会別シードに登録されています。既存行を編集してください。',
                ])
                ->withInput();
        }

        if ($seedPlayerId !== null) {
            $seedPlayer = TournamentSeedPlayer::query()
                ->where('tournament_id', $tournament->id)
                ->where('id', $seedPlayerId)
                ->where('is_active', true)
                ->firstOrFail();

            $seedPlayer->update([
                'pro_bowler_id' => $bowler?->id,
                'license_no' => $licenseNo,
                'seed_source_type' => $validated['seed_source_type'],
                'priority_order' => $validated['priority_order'] ?? null,
                'display_label' => $validated['display_label'] ?? null,
                'note' => $validated['note'] ?? null,
                'is_active' => true,
            ]);

            return redirect()
                ->route('tournaments.seed_players.index', $tournament)
                ->with('success', '大会別シードを更新しました。');
        }

        $seedService->addTournamentSeed(
            tournament: $tournament,
            bowler: $bowler,
            seedSourceType: $validated['seed_source_type'],
            attributes: [
                'license_no' => $licenseNo,
                'priority_order' => $validated['priority_order'] ?? null,
                'display_label' => $validated['display_label'] ?? null,
                'note' => $validated['note'] ?? null,
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('tournaments.seed_players.index', $tournament)
            ->with('success', '大会別シードを追加しました。');
    }

    public function destroy(Tournament $tournament, TournamentSeedPlayer $seedPlayer)
    {
        if ((int) $seedPlayer->tournament_id !== (int) $tournament->id) {
            abort(404);
        }

        $seedPlayer->update([
            'is_active' => false,
        ]);

        return redirect()
            ->route('tournaments.seed_players.index', $tournament)
            ->with('success', '大会別シードを解除しました。');
    }

    private function activeSeedPlayers(Tournament $tournament)
    {
        return TournamentSeedPlayer::query()
            ->with([
                'bowler',
                'seedListPlayer',
                'rankingSnapshot',
                'sourceTournament',
                'title',
            ])
            ->where('tournament_id', $tournament->id)
            ->where('is_active', true)
            ->orderByRaw('priority_order is null')
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();
    }

    private function buildTournamentPriorityPlayers(Tournament $tournament, $seedPlayers): array
    {
        $rows = [];

        foreach ($this->annualSeedRowsForTournament($tournament) as $row) {
            $key = $this->priorityKey(
                proBowlerId: $row->pro_bowler_id ? (int) $row->pro_bowler_id : null,
                licenseNo: $row->license_no ?? null
            );

            $rows[$key] = [
                'sort' => (int) ($row->priority_order ?? $row->seed_rank ?? $row->ranking_rank ?? 9999),
                'priority_order' => $row->priority_order ?? $row->seed_rank ?? $row->ranking_rank ?? null,
                'license_no' => $this->displayLicenseNo($row->license_no ?? null),
                'name' => $row->name_kanji ?? $row->name ?? $row->display_name ?? '-',
                'kana' => $row->name_kana ?? '',
                'period_label' => $this->formatPeriodLabel($row->bowler_period ?? null),
                'source_label' => '年度別シード',
                'seed_label' => $this->seedCategoryLabel($row->seed_category ?? null),
                'ranking_rank' => $row->ranking_rank ?? null,
                'base_ranking_year' => $row->base_ranking_year ?? null,
                'note' => '前年度ランキング由来',
                'seed_source_type' => ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_RANKING_TOP24,
                'seed_source_types' => [ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_RANKING_TOP24],
                'seed_category' => $row->seed_category ?? null,
            ];
        }

        foreach ($seedPlayers as $seedPlayer) {
            $key = $this->priorityKey(
                proBowlerId: $seedPlayer->pro_bowler_id ? (int) $seedPlayer->pro_bowler_id : null,
                licenseNo: $seedPlayer->license_no
            );

            $sourceLabel = $this->seedSourceOptions()[$seedPlayer->seed_source_type] ?? $seedPlayer->seed_source_type;
            $bowlerName = $seedPlayer->bowler->name_kanji
                ?? $seedPlayer->bowler->name
                ?? $seedPlayer->bowler->display_name
                ?? '-';
            $bowlerKana = $seedPlayer->bowler->name_kana ?? '';
            $bowlerPeriod = $this->formatPeriodLabel($seedPlayer->bowler->kibetsu ?? null);

            if (isset($rows[$key])) {
                if (($rows[$key]['period_label'] ?? '') === '' && $bowlerPeriod !== '') {
                    $rows[$key]['period_label'] = $bowlerPeriod;
                }

                $rows[$key]['source_label'] .= ' / 大会別追加';
                $rows[$key]['seed_source_types'] = array_values(array_unique(array_filter(array_merge(
                    $rows[$key]['seed_source_types'] ?? [],
                    [$seedPlayer->seed_source_type]
                ))));

                if (!$this->isTournamentSeedSourceType($rows[$key]['seed_source_type'] ?? null)) {
                    $rows[$key]['seed_source_type'] = $seedPlayer->seed_source_type;
                    $rows[$key]['seed_label'] = $seedPlayer->display_label ?: $sourceLabel;
                }

                $rows[$key]['note'] = trim(($rows[$key]['note'] ?? '') . ' ' . ($seedPlayer->note ?? ''));
                if ($seedPlayer->priority_order !== null) {
                    $rows[$key]['sort'] = (int) $seedPlayer->priority_order;
                    $rows[$key]['priority_order'] = $seedPlayer->priority_order;
                }
                continue;
            }

            $rows[$key] = [
                'sort' => (int) ($seedPlayer->priority_order ?? 9000 + (int) $seedPlayer->id),
                'priority_order' => $seedPlayer->priority_order,
                'license_no' => $this->displayLicenseNo($seedPlayer->license_no),
                'name' => $bowlerName,
                'kana' => $bowlerKana,
                'period_label' => $bowlerPeriod,
                'source_label' => '大会別追加',
                'seed_label' => $seedPlayer->display_label ?: $sourceLabel,
                'ranking_rank' => $seedPlayer->ranking_rank,
                'base_ranking_year' => null,
                'note' => $seedPlayer->note ?? '',
                'seed_source_type' => $seedPlayer->seed_source_type,
                'seed_source_types' => [$seedPlayer->seed_source_type],
                'seed_category' => null,
            ];
        }

        $rows = array_values($rows);

        usort($rows, function (array $a, array $b) {
            return [$a['sort'], $a['license_no'], $a['name']] <=> [$b['sort'], $b['license_no'], $b['name']];
        });

        foreach ($rows as $index => &$row) {
            $row['priority_no'] = $index + 1;
        }

        return $rows;
    }

    private function annualSeedRowsForTournament(Tournament $tournament)
    {
        if (($tournament->include_annual_seeds ?? true) === false) {
            return collect();
        }

        if (!Schema::hasTable('pro_bowler_seed_lists') || !Schema::hasTable('pro_bowler_seed_list_players')) {
            return collect();
        }

        $seedYear = $this->resolveTournamentYear($tournament);
        if ($seedYear === null) {
            return collect();
        }

        $gender = $this->normalizeGenderCode($tournament->gender ?? null);
        $periodSelect = Schema::hasColumn('pro_bowlers', 'kibetsu')
            ? 'b.kibetsu as bowler_period'
            : DB::raw('null as bowler_period');

        $query = DB::table('pro_bowler_seed_list_players as p')
            ->join('pro_bowler_seed_lists as l', 'l.id', '=', 'p.seed_list_id')
            ->leftJoin('pro_bowlers as b', 'b.id', '=', 'p.pro_bowler_id')
            ->where('l.seed_year', $seedYear)
            ->where('l.seed_list_type', 'tournament_seed')
            ->where('l.is_active', true)
            ->where('p.is_active', true)
            ->select([
                'p.id',
                'p.pro_bowler_id',
                'p.license_no',
                'p.seed_category',
                'p.seed_rank',
                'p.ranking_rank',
                'p.priority_order',
                'p.note',
                'l.base_ranking_year',
                'l.gender',
                'b.name_kanji',
                'b.name_kana',
                $periodSelect,
            ]);

        if ($gender !== null) {
            $query->where(function ($q) use ($gender) {
                $q->where('l.gender', $gender)
                    ->orWhereNull('l.gender');
            });
        }

        if ((int) ($tournament->annual_seed_rank_limit ?? 0) > 0) {
            $limit = (int) $tournament->annual_seed_rank_limit;
            $query->where(function ($q) use ($limit) {
                $q->where('p.seed_rank', '<=', $limit)
                    ->orWhere(function ($q) use ($limit) {
                        $q->whereNull('p.seed_rank')->where('p.ranking_rank', '<=', $limit);
                    });
            });
        }

        return $query
            ->orderByRaw('p.priority_order is null')
            ->orderBy('p.priority_order')
            ->orderBy('p.seed_rank')
            ->orderBy('p.ranking_rank')
            ->orderBy('p.id')
            ->get();
    }

    private function annualSeedOverlapMap(Tournament $tournament, $seedPlayers): array
    {
        $annualSeedKeys = [];

        foreach ($this->annualSeedRowsForTournament($tournament) as $row) {
            $annualSeedKeys[$this->priorityKey(
                proBowlerId: $row->pro_bowler_id ? (int) $row->pro_bowler_id : null,
                licenseNo: $row->license_no ?? null
            )] = true;
        }

        $overlapMap = [];
        foreach ($seedPlayers as $seedPlayer) {
            $key = $this->priorityKey(
                proBowlerId: $seedPlayer->pro_bowler_id ? (int) $seedPlayer->pro_bowler_id : null,
                licenseNo: $seedPlayer->license_no
            );
            $overlapMap[(int) $seedPlayer->id] = isset($annualSeedKeys[$key]);
        }

        return $overlapMap;
    }

    private function findDuplicateActiveSeedPlayer(
        Tournament $tournament,
        string $licenseNo,
        ?ProBowler $bowler,
        ?int $ignoreSeedPlayerId = null
    ): ?TournamentSeedPlayer {
        $last4 = $this->last4($licenseNo);

        $query = TournamentSeedPlayer::query()
            ->where('tournament_id', $tournament->id)
            ->where('is_active', true);

        if ($ignoreSeedPlayerId !== null) {
            $query->where('id', '<>', $ignoreSeedPlayerId);
        }

        $query->where(function ($q) use ($licenseNo, $last4, $bowler) {
            if ($bowler !== null) {
                $q->orWhere('pro_bowler_id', $bowler->id);
                $q->orWhereRaw("UPPER(TRIM(COALESCE(license_no, ''))) = ?", [$licenseNo]);

                return;
            }

            $q->orWhereRaw("UPPER(TRIM(COALESCE(license_no, ''))) = ?", [$licenseNo]);

            if ($last4 !== null) {
                $q->orWhereRaw("RIGHT(UPPER(TRIM(COALESCE(license_no, ''))), 4) = ?", [$last4]);
            }
        });

        return $query->orderBy('id')->first();
    }

    private function formatPeriodLabel($value): string
    {
        if ($value === null) {
            return '';
        }

        $label = trim((string) $value);

        if ($label === '') {
            return '';
        }

        $digits = preg_replace('/[^0-9０-９]+/u', '', $label) ?? '';

        if ($digits !== '') {
            return mb_convert_kana($digits, 'n', 'UTF-8');
        }

        return $label;
    }

    private function seedSourceOptions(): array
    {
        return [
            ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_RANKING_TOP24 => '① トーナメントシード（前年度ランキング上位24名）',
            ProBowlerSeedService::SOURCE_CURRENT_YEAR_RANKING => '① トーナメントシード（当該年度ランキング）',
            ProBowlerSeedService::SOURCE_PAST_CHAMPION => '② 公認T/M歴代優勝者シードプロ',
            ProBowlerSeedService::SOURCE_PERMANENT_SEED => '③ 永久シードプロ（V20）',
            ProBowlerSeedService::SOURCE_ALL_JAPAN_CHAMPION => '④ 全日本選手権者シード（JS）',
            ProBowlerSeedService::SOURCE_CURRENT_YEAR_WINNER => '⑤ 当該年度優勝者シード',
            ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_WINNER => '⑤ 前年度優勝者シード',
            ProBowlerSeedService::SOURCE_SEMI_PERMANENT_SEED => '⑥ 準永久シードプロ（V10）',
            ProBowlerSeedService::SOURCE_EVENT_SPONSOR_RECOMMENDATION => '⑦ 本大会スポンサー推薦',
            ProBowlerSeedService::SOURCE_PRO_TEST_PRACTICAL_EXEMPT => '⑧ プロテスト実技免除合格者',
            ProBowlerSeedService::SOURCE_PRO_TEST_TOP_PASSER => '⑨ プロテストトップ合格者',
            ProBowlerSeedService::SOURCE_SEASON_TRIAL_PARTICIPANT => '⑩ シーズントライアル出場者',
            ProBowlerSeedService::SOURCE_TOURNAMENT_QUALIFIER => '⑪ 前段階大会通過者',
            ProBowlerSeedService::SOURCE_ORGANIZER_RECOMMENDATION => '⑪ 主催者（スポンサー）推薦',
            ProBowlerSeedService::SOURCE_MANUAL => 'その他：手動追加',
        ];
    }

    private function seedCategoryLabel(?string $seedCategory): string
    {
        return match ($seedCategory) {
            ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED => 'トーナメントシード',
            ProBowlerSeedService::SEED_CATEGORY_PERMANENT => '永久シード',
            ProBowlerSeedService::SEED_CATEGORY_SEMI_PERMANENT => '準永久シード',
            ProBowlerSeedService::SEED_CATEGORY_ALL_JAPAN => '全日本選手権者シード',
            ProBowlerSeedService::SEED_CATEGORY_CURRENT_YEAR_WINNER => '当該年度優勝者シード',
            ProBowlerSeedService::SEED_CATEGORY_PREVIOUS_YEAR_WINNER => '前年度優勝者シード',
            ProBowlerSeedService::SEED_CATEGORY_PAST_CHAMPION => '歴代優勝者',
            ProBowlerSeedService::SEED_CATEGORY_MANUAL => '手動追加',
            default => $seedCategory ?: '-',
        };
    }

    private function findBowlerByLicenseNo(string $licenseNo, Tournament $tournament): ?ProBowler
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);
        $last4 = $this->last4($licenseNo);
        $gender = $this->normalizeGenderCode($tournament->gender ?? null);
        $candidates = array_values(array_unique(array_filter([
            $licenseNo,
            $last4,
        ])));

        $licenseColumns = collect(['license_no', 'license_number', 'pro_bowler_license_no'])
            ->filter(fn (string $column) => Schema::hasColumn('pro_bowlers', $column))
            ->values();

        if ($licenseColumns->isEmpty() || empty($candidates)) {
            return null;
        }

        $query = ProBowler::query()
            ->where(function ($query) use ($licenseColumns, $candidates) {
                foreach ($licenseColumns as $column) {
                    foreach ($candidates as $candidate) {
                        $query->orWhereRaw('UPPER(TRIM(COALESCE(' . $column . ", ''))) = ?", [$candidate]);
                        $query->orWhereRaw('RIGHT(UPPER(TRIM(COALESCE(' . $column . ", ''))), 4) = ?", [$candidate]);
                    }
                }
            });

        $this->applyTournamentGenderFilter($query, $gender, $licenseColumns);

        return $query
            ->orderBy('id')
            ->first();
    }

    private function canonicalLicenseNo(string $inputLicenseNo, ?ProBowler $bowler): string
    {
        if ($bowler === null) {
            return $inputLicenseNo;
        }

        foreach (['license_no', 'license_number', 'pro_bowler_license_no'] as $column) {
            if (isset($bowler->{$column}) && trim((string) $bowler->{$column}) !== '') {
                return $this->normalizeLicenseNo($bowler->{$column});
            }
        }

        return $inputLicenseNo;
    }

    private function applyTournamentGenderFilter($query, ?string $gender, $licenseColumns): void
    {
        if ($gender === null) {
            return;
        }

        $sexId = $gender === 'M' ? 1 : 2;
        $prefix = $gender . '%';

        $query->where(function ($genderQuery) use ($sexId, $prefix, $licenseColumns) {
            if (Schema::hasColumn('pro_bowlers', 'sex')) {
                $genderQuery->orWhere('sex', $sexId);
            }

            foreach ($licenseColumns as $column) {
                $genderQuery->orWhereRaw('UPPER(TRIM(COALESCE(' . $column . ", ''))) LIKE ?", [$prefix]);
            }
        });
    }

    private function genderFromLicenseNo(?string $licenseNo): ?string
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        if (str_starts_with($licenseNo, 'M')) {
            return 'M';
        }

        if (str_starts_with($licenseNo, 'F')) {
            return 'F';
        }

        return null;
    }

    private function resolveTournamentYear(Tournament $tournament): ?int
    {
        if (isset($tournament->year) && $tournament->year !== null && $tournament->year !== '') {
            return (int) $tournament->year;
        }

        if (isset($tournament->start_date) && $tournament->start_date !== null && $tournament->start_date !== '') {
            return (int) mb_substr((string) $tournament->start_date, 0, 4);
        }

        return null;
    }

    private function normalizeGenderCode($gender): ?string
    {
        $gender = trim((string) $gender);

        return match ($gender) {
            '1', 'M', 'm', 'male', 'Male', '男子', '男' => 'M',
            '2', 'F', 'f', 'female', 'Female', '女子', '女' => 'F',
            default => null,
        };
    }

    private function isTournamentSeedSourceType(?string $seedSourceType): bool
    {
        return in_array($seedSourceType, [
            ProBowlerSeedService::SOURCE_SEED_LIST,
            ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_RANKING_TOP24,
            ProBowlerSeedService::SOURCE_CURRENT_YEAR_RANKING,
        ], true);
    }

    private function priorityKey(?int $proBowlerId, ?string $licenseNo): string
    {
        if ($proBowlerId !== null) {
            return 'pro:' . $proBowlerId;
        }

        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        return 'license:' . ($licenseNo ?: uniqid('unknown_', true));
    }

    private function displayLicenseNo(?string $licenseNo): string
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        if ($licenseNo === '') {
            return '-';
        }

        return mb_substr($licenseNo, -4);
    }

    private function normalizeLicenseNo(?string $licenseNo): string
    {
        return strtoupper(trim((string) $licenseNo));
    }

    private function pdfFileName(Tournament $tournament): string
    {
        $year = $this->resolveTournamentYear($tournament) ?: date('Y');
        $name = trim((string) ($tournament->name ?? 'tournament'));
        $name = $name !== '' ? $name : 'tournament';

        $fileName = $year . '_' . $name . '_大会優先出場者一覧.pdf';
        $fileName = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '_', $fileName);
        $fileName = trim($fileName, " \t\n\r\0\x0B._");

        return $fileName !== '' ? $fileName : 'priority_list.pdf';
    }

    private function last4(?string $licenseNo): ?string
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        if ($licenseNo === '') {
            return null;
        }

        return mb_substr($licenseNo, -4);
    }
}
