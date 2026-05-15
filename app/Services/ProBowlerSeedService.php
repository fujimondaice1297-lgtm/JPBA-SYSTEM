<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProBowlerRankingRow;
use App\Models\ProBowlerRankingSnapshot;
use App\Models\ProBowlerSeedList;
use App\Models\ProBowlerSeedListPlayer;
use App\Models\Tournament;
use App\Models\TournamentSeedPlayer;
use Illuminate\Support\Collection;
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
     */
    public function isSeedPlayer(
        int $tournamentId,
        ?int $proBowlerId = null,
        ?string $licenseNo = null
    ): bool {
        return $this->queryActiveTournamentSeedPlayer($tournamentId, $proBowlerId, $licenseNo)->exists();
    }

    /**
     * 大会内のシード対象を、pro_bowler_id / license_no の両方で引けるmapにする。
     *
     * @return array<string,TournamentSeedPlayer>
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
            self::SOURCE_PAST_CHAMPION => '歴代優勝者',
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