<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProBowlerRankingRow;
use App\Models\ProBowlerRankingSnapshot;
use App\Models\ProBowlerSeedList;
use App\Models\ProBowlerSeedListPlayer;
use App\Models\Tournament;
use App\Models\TournamentSeedPlayer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProBowlerSeedService
{
    public const SEED_CATEGORY_TOURNAMENT_SEED = 'TS';
    public const SEED_CATEGORY_PERMANENT = 'V20';
    public const SEED_CATEGORY_SEMI_PERMANENT = 'V10';
    public const SEED_CATEGORY_ALL_JAPAN = 'JS';
    public const SEED_CATEGORY_CURRENT_YEAR_WINNER = 'CS1';
    public const SEED_CATEGORY_PREVIOUS_YEAR_WINNER = 'CS2';
    public const SEED_CATEGORY_PAST_CHAMPION = 'PAST_CHAMPION';
    public const SEED_CATEGORY_MANUAL = 'MANUAL';

    public const SOURCE_SEED_LIST = 'seed_list';
    public const SOURCE_PREVIOUS_YEAR_RANKING_TOP24 = 'previous_year_ranking_top24';
    public const SOURCE_CURRENT_YEAR_RANKING = 'current_year_ranking';
    public const SOURCE_PERMANENT_SEED = 'permanent_seed';
    public const SOURCE_SEMI_PERMANENT_SEED = 'semi_permanent_seed';
    public const SOURCE_ALL_JAPAN_CHAMPION = 'all_japan_champion';
    public const SOURCE_CURRENT_YEAR_WINNER = 'current_year_winner';
    public const SOURCE_PREVIOUS_YEAR_WINNER = 'previous_year_winner';
    public const SOURCE_PAST_CHAMPION = 'past_champion';
    public const SOURCE_EVENT_SPONSOR_RECOMMENDATION = 'event_sponsor_recommendation';
    public const SOURCE_ORGANIZER_RECOMMENDATION = 'organizer_recommendation';
    public const SOURCE_PRO_TEST_PRACTICAL_EXEMPT = 'pro_test_practical_exempt';
    public const SOURCE_PRO_TEST_TOP_PASSER = 'pro_test_top_passer';
    public const SOURCE_SEASON_TRIAL_PARTICIPANT = 'season_trial_participant';
    public const SOURCE_MANUAL = 'manual';

    /**
     * 前年度ランキングなどの snapshot から、年度別トーナメントシード一覧を作る。
     *
     * 通常運用:
     * - 前年度ランキング上位24名を TS として登録する。
     * - ranking row 側の当時表示を残しつつ、pro_bowler_id が取れる行はIDでも紐づける。
     */
    public function createSeedListFromRanking(
        ProBowlerRankingSnapshot $rankingSnapshot,
        int $seedYear,
        ?string $gender = null,
        int $topCount = 24,
        array $options = []
    ): ProBowlerSeedList {
        if ($topCount < 1) {
            throw new InvalidArgumentException('topCount must be greater than 0.');
        }

        return DB::transaction(function () use ($rankingSnapshot, $seedYear, $gender, $topCount, $options) {
            $seedList = ProBowlerSeedList::query()->firstOrNew([
                'seed_year' => $seedYear,
                'gender' => $gender ?: $rankingSnapshot->gender,
                'seed_list_type' => $options['seed_list_type'] ?? 'tournament_seed',
            ]);

            $seedList->fill([
                'source_ranking_snapshot_id' => $rankingSnapshot->id,
                'base_ranking_year' => $rankingSnapshot->ranking_year,
                'base_top_count' => $topCount,
                'as_of_date' => $options['as_of_date'] ?? $rankingSnapshot->as_of_date,
                'is_active' => $options['is_active'] ?? true,
                'source_url' => $options['source_url'] ?? $rankingSnapshot->source_url,
                'notes' => $options['notes'] ?? null,
            ]);
            $seedList->save();

            $rows = $rankingSnapshot->rows()
                ->where('ranking_rank', '<=', $topCount)
                ->orderBy('ranking_rank')
                ->get();

            foreach ($rows as $row) {
                $this->upsertSeedListPlayerFromRankingRow(
                    seedList: $seedList,
                    rankingSnapshot: $rankingSnapshot,
                    row: $row,
                    seedCategory: self::SEED_CATEGORY_TOURNAMENT_SEED
                );
            }

            return $seedList->fresh(['players']);
        });
    }

    /**
     * 年度別シード一覧を、大会別シードへ展開する。
     *
     * PDFの S 表示や大会別優先順位判定では tournament_seed_players を参照できるようにする。
     */
    public function syncTournamentSeedsFromSeedList(
        Tournament $tournament,
        ProBowlerSeedList $seedList,
        ?int $limit = null
    ): int {
        return DB::transaction(function () use ($tournament, $seedList, $limit) {
            $query = $seedList->activePlayers()
                ->orderBy('priority_order')
                ->orderBy('seed_rank')
                ->orderBy('id');

            if ($limit !== null && $limit > 0) {
                $query->limit($limit);
            }

            $count = 0;

            foreach ($query->get() as $seedListPlayer) {
                $this->upsertTournamentSeedFromSeedListPlayer($tournament, $seedListPlayer);
                $count++;
            }

            return $count;
        });
    }

    /**
     * 大会ごとの追加シードを手動登録する。
     *
     * 歴代優勝者枠、永久シード、特別シードなど、ランキング一覧とは別に足す場合に使う。
     */
    public function addTournamentSeed(
        Tournament $tournament,
        ?ProBowler $bowler,
        string $seedSourceType = self::SOURCE_MANUAL,
        array $attributes = []
    ): TournamentSeedPlayer {
        $licenseNo = $this->normalizeLicenseNo(
            $attributes['license_no'] ?? $bowler?->license_no
        );

        if ($bowler === null && $licenseNo === null) {
            throw new InvalidArgumentException('A bowler or license_no is required.');
        }

        return DB::transaction(function () use ($tournament, $bowler, $seedSourceType, $attributes, $licenseNo) {
            $seedPlayer = $this->findTournamentSeedPlayer(
                tournamentId: (int) $tournament->id,
                licenseNo: $licenseNo,
                proBowlerId: $bowler?->id,
                seedSourceType: $seedSourceType
            );

            $seedPlayer->fill([
                'tournament_id' => $tournament->id,
                'pro_bowler_id' => $bowler?->id,
                'license_no' => $licenseNo,
                'seed_source_type' => $seedSourceType,
                'seed_list_player_id' => $attributes['seed_list_player_id'] ?? null,
                'ranking_snapshot_id' => $attributes['ranking_snapshot_id'] ?? null,
                'ranking_rank' => $attributes['ranking_rank'] ?? null,
                'source_tournament_id' => $attributes['source_tournament_id'] ?? null,
                'pro_bowler_title_id' => $attributes['pro_bowler_title_id'] ?? null,
                'priority_order' => $attributes['priority_order'] ?? null,
                'display_label' => $attributes['display_label'] ?? $this->defaultDisplayLabelForSource($seedSourceType),
                'note' => $attributes['note'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);

            $seedPlayer->save();

            return $seedPlayer;
        });
    }

    /**
     * 大会内で対象選手がシード扱いか判定する。
     *
     * 判定順:
     * 1. 大会別シード tournament_seed_players
     * 2. 年度別シード pro_bowler_seed_lists / pro_bowler_seed_list_players
     *
     * 年度別シードは大会の gender ではなく、選手本人の sex / ライセンス接頭辞を優先する。
     * 大会マスタの gender が誤っていても、女子選手は女子シード一覧で判定できるようにする。
     */
    public function isSeedPlayer(
        int $tournamentId,
        ?int $proBowlerId = null,
        ?string $licenseNo = null
    ): bool {
        if ($this->queryActiveTournamentSeedPlayer($tournamentId, $proBowlerId, $licenseNo)->exists()) {
            return true;
        }

        return $this->queryActiveAnnualSeedListPlayer($tournamentId, $proBowlerId, $licenseNo)->exists();
    }

    /**
     * 大会内のシード対象を、pro_bowler_id / license_no の両方で引けるmapにする。
     *
     * @return array<string,mixed>
     */
    public function seedMapForTournament(int $tournamentId): array
    {
        $map = [];

        TournamentSeedPlayer::query()
            ->where('tournament_id', $tournamentId)
            ->where('is_active', true)
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get()
            ->each(function (TournamentSeedPlayer $seedPlayer) use (&$map) {
                if ($seedPlayer->pro_bowler_id) {
                    $map['pro_bowler:' . $seedPlayer->pro_bowler_id] = $seedPlayer;
                }

                $licenseNo = $this->normalizeLicenseNo($seedPlayer->license_no);
                if ($licenseNo !== null) {
                    $map['license:' . $licenseNo] = $seedPlayer;
                }
            });

        foreach ($this->annualSeedListPlayersForTournament($tournamentId) as $seedListPlayer) {
            if ($seedListPlayer->pro_bowler_id) {
                $map['pro_bowler:' . $seedListPlayer->pro_bowler_id] = $seedListPlayer;
            }

            $licenseNo = $this->normalizeLicenseNo($seedListPlayer->license_no);
            if ($licenseNo !== null) {
                $map['license:' . $licenseNo] = $seedListPlayer;
            }
        }

        return $map;
    }

    /**
     * PDF用のライセンスNo表示を返す。
     *
     * 例:
     * - 通常: 1443
     * - シード: S 1443
     */
    public function formatLicenseForPdf(?string $licenseNo, bool $isSeed = false): string
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        if ($licenseNo === null) {
            return $isSeed ? 'S' : '';
        }

        $last4 = mb_substr($licenseNo, -4);
        $display = $last4 !== '' ? $last4 : $licenseNo;

        return $isSeed ? ('S ' . $display) : $display;
    }

    /**
     * 大会IDと選手情報から、PDF用ライセンスNo表示を返す。
     */
    public function formatLicenseForTournamentPdf(
        int $tournamentId,
        ?int $proBowlerId,
        ?string $licenseNo
    ): string {
        return $this->formatLicenseForPdf(
            licenseNo: $licenseNo,
            isSeed: $this->isSeedPlayer($tournamentId, $proBowlerId, $licenseNo)
        );
    }

    /**
     * 大会内のシード情報を1件返す。
     *
     * 注意:
     * ここは大会別シード設定画面など、tournament_seed_players の実体が必要な場面用。
     * 年度別シードは tournament_seed_players に展開しない限り、この戻り値には含めない。
     */
    public function findActiveSeedPlayer(
        int $tournamentId,
        ?int $proBowlerId = null,
        ?string $licenseNo = null
    ): ?TournamentSeedPlayer {
        return $this->queryActiveTournamentSeedPlayer($tournamentId, $proBowlerId, $licenseNo)
            ->orderBy('priority_order')
            ->orderBy('id')
            ->first();
    }

    private function upsertSeedListPlayerFromRankingRow(
        ProBowlerSeedList $seedList,
        ProBowlerRankingSnapshot $rankingSnapshot,
        ProBowlerRankingRow $row,
        string $seedCategory
    ): ProBowlerSeedListPlayer {
        $licenseNo = $this->normalizeLicenseNo($row->license_no);

        $seedPlayer = $this->findSeedListPlayer(
            seedListId: (int) $seedList->id,
            licenseNo: $licenseNo,
            proBowlerId: $row->pro_bowler_id ? (int) $row->pro_bowler_id : null,
            seedCategory: $seedCategory
        );

        $seedPlayer->fill([
            'seed_list_id' => $seedList->id,
            'pro_bowler_id' => $row->pro_bowler_id,
            'license_no' => $licenseNo,
            'seed_category' => $seedCategory,
            'seed_rank' => $row->ranking_rank,
            'ranking_snapshot_id' => $rankingSnapshot->id,
            'ranking_rank' => $row->ranking_rank,
            'source_tournament_id' => null,
            'pro_bowler_title_id' => null,
            'priority_order' => $row->ranking_rank,
            'note' => null,
            'is_active' => true,
        ]);

        $seedPlayer->save();

        return $seedPlayer;
    }

    private function upsertTournamentSeedFromSeedListPlayer(
        Tournament $tournament,
        ProBowlerSeedListPlayer $seedListPlayer
    ): TournamentSeedPlayer {
        $licenseNo = $this->normalizeLicenseNo($seedListPlayer->license_no);
        $sourceType = $this->sourceTypeFromSeedCategory($seedListPlayer->seed_category);

        $seedPlayer = $this->findTournamentSeedPlayer(
            tournamentId: (int) $tournament->id,
            licenseNo: $licenseNo,
            proBowlerId: $seedListPlayer->pro_bowler_id ? (int) $seedListPlayer->pro_bowler_id : null,
            seedSourceType: $sourceType
        );

        $seedPlayer->fill([
            'tournament_id' => $tournament->id,
            'pro_bowler_id' => $seedListPlayer->pro_bowler_id,
            'license_no' => $licenseNo,
            'seed_source_type' => $sourceType,
            'seed_list_player_id' => $seedListPlayer->id,
            'ranking_snapshot_id' => $seedListPlayer->ranking_snapshot_id,
            'ranking_rank' => $seedListPlayer->ranking_rank,
            'source_tournament_id' => $seedListPlayer->source_tournament_id,
            'pro_bowler_title_id' => $seedListPlayer->pro_bowler_title_id,
            'priority_order' => $seedListPlayer->priority_order ?? $seedListPlayer->seed_rank,
            'display_label' => $this->defaultDisplayLabelForCategory($seedListPlayer->seed_category),
            'note' => $seedListPlayer->note,
            'is_active' => $seedListPlayer->is_active,
        ]);

        $seedPlayer->save();

        return $seedPlayer;
    }

    private function findSeedListPlayer(
        int $seedListId,
        ?string $licenseNo,
        ?int $proBowlerId,
        string $seedCategory
    ): ProBowlerSeedListPlayer {
        $query = ProBowlerSeedListPlayer::query()
            ->where('seed_list_id', $seedListId)
            ->where('seed_category', $seedCategory);

        if ($licenseNo !== null) {
            $query->where('license_no', $licenseNo);
        } elseif ($proBowlerId !== null) {
            $query->where('pro_bowler_id', $proBowlerId);
        } else {
            return new ProBowlerSeedListPlayer();
        }

        return $query->first() ?: new ProBowlerSeedListPlayer();
    }

    private function findTournamentSeedPlayer(
        int $tournamentId,
        ?string $licenseNo,
        ?int $proBowlerId,
        string $seedSourceType
    ): TournamentSeedPlayer {
        $query = TournamentSeedPlayer::query()
            ->where('tournament_id', $tournamentId)
            ->where('seed_source_type', $seedSourceType);

        if ($licenseNo !== null) {
            $query->where('license_no', $licenseNo);
        } elseif ($proBowlerId !== null) {
            $query->where('pro_bowler_id', $proBowlerId);
        } else {
            return new TournamentSeedPlayer();
        }

        return $query->first() ?: new TournamentSeedPlayer();
    }

    private function queryActiveTournamentSeedPlayer(
        int $tournamentId,
        ?int $proBowlerId,
        ?string $licenseNo
    ) {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        $query = TournamentSeedPlayer::query()
            ->where('tournament_id', $tournamentId)
            ->where('is_active', true);

        if ($proBowlerId !== null && $licenseNo !== null) {
            return $query->where(function ($q) use ($proBowlerId, $licenseNo) {
                $q->where('pro_bowler_id', $proBowlerId)
                    ->orWhere('license_no', $licenseNo);
            });
        }

        if ($proBowlerId !== null) {
            return $query->where('pro_bowler_id', $proBowlerId);
        }

        if ($licenseNo !== null) {
            return $query->where('license_no', $licenseNo);
        }

        return $query->whereRaw('1 = 0');
    }

    private function queryActiveAnnualSeedListPlayer(
        int $tournamentId,
        ?int $proBowlerId,
        ?string $licenseNo
    ) {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);
        $tournament = Tournament::query()->find($tournamentId);

        if (!$tournament) {
            return ProBowlerSeedListPlayer::query()->whereRaw('1 = 0');
        }

        $seedYear = $this->resolveSeedYear($tournament);
        if ($seedYear === null) {
            return ProBowlerSeedListPlayer::query()->whereRaw('1 = 0');
        }

        $bowler = $this->resolveBowler($proBowlerId, $licenseNo);
        $genderCandidates = $this->genderCandidatesForAnnualSeed($tournament, $bowler, $licenseNo);

        if (empty($genderCandidates)) {
            return ProBowlerSeedListPlayer::query()->whereRaw('1 = 0');
        }

        $seedListIds = ProBowlerSeedList::query()
            ->where('seed_year', $seedYear)
            ->where('seed_list_type', 'tournament_seed')
            ->where('is_active', true)
            ->where(function ($q) use ($genderCandidates) {
                $q->whereIn('gender', $genderCandidates)
                    ->orWhereNull('gender');
            })
            ->pluck('id');

        if ($seedListIds->isEmpty()) {
            return ProBowlerSeedListPlayer::query()->whereRaw('1 = 0');
        }

        $query = ProBowlerSeedListPlayer::query()
            ->whereIn('seed_list_id', $seedListIds)
            ->where('is_active', true);

        if ($proBowlerId !== null && $licenseNo !== null) {
            return $query->where(function ($q) use ($proBowlerId, $licenseNo) {
                $q->where('pro_bowler_id', $proBowlerId)
                    ->orWhere('license_no', $licenseNo);
            });
        }

        if ($proBowlerId !== null) {
            return $query->where('pro_bowler_id', $proBowlerId);
        }

        if ($licenseNo !== null) {
            return $query->where('license_no', $licenseNo);
        }

        return $query->whereRaw('1 = 0');
    }

    private function annualSeedListPlayersForTournament(int $tournamentId)
    {
        $tournament = Tournament::query()->find($tournamentId);

        if (!$tournament) {
            return collect();
        }

        $seedYear = $this->resolveSeedYear($tournament);
        if ($seedYear === null) {
            return collect();
        }

        $seedListIds = ProBowlerSeedList::query()
            ->where('seed_year', $seedYear)
            ->where('seed_list_type', 'tournament_seed')
            ->where('is_active', true)
            ->pluck('id');

        if ($seedListIds->isEmpty()) {
            return collect();
        }

        return ProBowlerSeedListPlayer::query()
            ->whereIn('seed_list_id', $seedListIds)
            ->where('is_active', true)
            ->orderBy('priority_order')
            ->orderBy('seed_rank')
            ->orderBy('id')
            ->get();
    }

    private function resolveBowler(?int $proBowlerId, ?string $licenseNo): ?ProBowler
    {
        if ($proBowlerId !== null) {
            $bowler = ProBowler::query()->find($proBowlerId);
            if ($bowler) {
                return $bowler;
            }
        }

        $licenseNo = $this->normalizeLicenseNo($licenseNo);
        if ($licenseNo === null) {
            return null;
        }

        $last4 = mb_substr($licenseNo, -4);

        return ProBowler::query()
            ->where('license_no', $licenseNo)
            ->when($last4 !== '', function ($query) use ($last4) {
                $numeric = (int) $last4;

                $query->orWhere('license_no_num', $numeric);
            })
            ->orderBy('id')
            ->first();
    }

    private function resolveSeedYear(Tournament $tournament): ?int
    {
        if (isset($tournament->year) && $tournament->year !== null && $tournament->year !== '') {
            return (int) $tournament->year;
        }

        if (isset($tournament->start_date) && $tournament->start_date !== null && $tournament->start_date !== '') {
            return (int) mb_substr((string) $tournament->start_date, 0, 4);
        }

        return null;
    }

    private function genderCandidatesForAnnualSeed(
        Tournament $tournament,
        ?ProBowler $bowler,
        ?string $licenseNo
    ): array {
        $candidates = [];

        $fromBowler = $this->genderCodeFromSex($bowler?->sex ?? null);
        if ($fromBowler !== null) {
            $candidates[] = $fromBowler;
        }

        $fromLicense = $this->genderCodeFromLicense($licenseNo);
        if ($fromLicense !== null) {
            $candidates[] = $fromLicense;
        }

        $fromTournament = $this->normalizeGenderCode($tournament->gender ?? null);
        if ($fromTournament !== null) {
            $candidates[] = $fromTournament;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function genderCodeFromSex($sex): ?string
    {
        $sex = trim((string) $sex);

        return match ($sex) {
            '1', 'M', 'm', 'male', 'Male', '男子', '男' => 'M',
            '2', 'F', 'f', 'female', 'Female', '女子', '女' => 'F',
            default => null,
        };
    }

    private function genderCodeFromLicense(?string $licenseNo): ?string
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);
        if ($licenseNo === null) {
            return null;
        }

        if (str_starts_with($licenseNo, 'M')) {
            return 'M';
        }

        if (str_starts_with($licenseNo, 'F')) {
            return 'F';
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

    private function sourceTypeFromSeedCategory(string $seedCategory): string
    {
        return match ($seedCategory) {
            self::SEED_CATEGORY_TOURNAMENT_SEED => self::SOURCE_PREVIOUS_YEAR_RANKING_TOP24,
            self::SEED_CATEGORY_PERMANENT => self::SOURCE_PERMANENT_SEED,
            self::SEED_CATEGORY_SEMI_PERMANENT => self::SOURCE_SEMI_PERMANENT_SEED,
            self::SEED_CATEGORY_ALL_JAPAN => self::SOURCE_ALL_JAPAN_CHAMPION,
            self::SEED_CATEGORY_CURRENT_YEAR_WINNER => self::SOURCE_CURRENT_YEAR_WINNER,
            self::SEED_CATEGORY_PREVIOUS_YEAR_WINNER => self::SOURCE_PREVIOUS_YEAR_WINNER,
            self::SEED_CATEGORY_PAST_CHAMPION => self::SOURCE_PAST_CHAMPION,
            self::SEED_CATEGORY_MANUAL => self::SOURCE_MANUAL,
            default => self::SOURCE_MANUAL,
        };
    }

    private function defaultDisplayLabelForCategory(string $seedCategory): string
    {
        return match ($seedCategory) {
            self::SEED_CATEGORY_TOURNAMENT_SEED => 'TS',
            self::SEED_CATEGORY_PERMANENT => 'V20',
            self::SEED_CATEGORY_SEMI_PERMANENT => 'V10',
            self::SEED_CATEGORY_ALL_JAPAN => 'JS',
            self::SEED_CATEGORY_CURRENT_YEAR_WINNER => 'CS1',
            self::SEED_CATEGORY_PREVIOUS_YEAR_WINNER => 'CS2',
            self::SEED_CATEGORY_PAST_CHAMPION => '歴代優勝者',
            self::SEED_CATEGORY_MANUAL => '手動',
            default => $seedCategory,
        };
    }

    private function defaultDisplayLabelForSource(string $seedSourceType): string
    {
        return match ($seedSourceType) {
            self::SOURCE_SEED_LIST => 'シード',
            self::SOURCE_PREVIOUS_YEAR_RANKING_TOP24 => 'TS',
            self::SOURCE_CURRENT_YEAR_RANKING => '当該年度ランキング',
            self::SOURCE_PERMANENT_SEED => 'V20',
            self::SOURCE_SEMI_PERMANENT_SEED => 'V10',
            self::SOURCE_ALL_JAPAN_CHAMPION => 'JS',
            self::SOURCE_CURRENT_YEAR_WINNER => 'CS1',
            self::SOURCE_PREVIOUS_YEAR_WINNER => 'CS2',
            self::SOURCE_PAST_CHAMPION => '公認T/M歴代優勝者シード',
            self::SOURCE_EVENT_SPONSOR_RECOMMENDATION => '本大会スポンサー推薦',
            self::SOURCE_ORGANIZER_RECOMMENDATION => '主催者推薦',
            self::SOURCE_PRO_TEST_PRACTICAL_EXEMPT => 'プロテスト実技免除合格者',
            self::SOURCE_PRO_TEST_TOP_PASSER => 'プロテストトップ合格者',
            self::SOURCE_SEASON_TRIAL_PARTICIPANT => 'シーズントライアル出場者',
            self::SOURCE_MANUAL => '手動',
            default => $seedSourceType,
        };
    }

    private function normalizeLicenseNo(?string $licenseNo): ?string
    {
        $licenseNo = strtoupper(trim((string) $licenseNo));

        return $licenseNo !== '' ? $licenseNo : null;
    }
}
