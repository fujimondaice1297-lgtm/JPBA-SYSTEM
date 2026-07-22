<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentResult;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultPublicationRow;
use App\Models\TournamentResultSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TournamentResultPublicationService
{
    public function __construct(
        private readonly TournamentResultPublicationCalculator $calculator,
        private readonly TournamentTitleSyncService $titleSyncService,
        private readonly TournamentResultCompletenessService $completenessService,
    ) {}

    /** @return array<string,mixed> */
    public function preview(Tournament $tournament, TournamentResultSnapshot $snapshot): array
    {
        $errors = [];
        $warnings = [];

        if ((int) $snapshot->tournament_id !== (int) $tournament->id) {
            $errors[] = '指定された最終成績はこの大会のものではありません。';
        }
        if (! $snapshot->is_final) {
            $errors[] = '最終成績として作成されたスナップショットだけを公開できます。';
        }
        if (! $snapshot->is_current) {
            $errors[] = '旧版のスナップショットは公開できません。最新の最終成績を選択してください。';
        }
        if (trim((string) ($snapshot->shift ?? '')) !== '') {
            $errors[] = 'シフト別の途中成績は大会最終成績として公開できません。';
        }

        $snapshotGender = strtoupper(trim((string) ($snapshot->gender ?? '')));
        $tournamentGender = strtoupper(trim((string) ($tournament->gender ?? '')));
        if ($snapshotGender !== ''
            && $tournamentGender !== ''
            && $tournamentGender !== 'X'
            && $snapshotGender !== $tournamentGender) {
            $errors[] = '大会と最終成績の性別が一致していません。';
        }
        if ($tournamentGender === 'X' && $snapshotGender !== '') {
            $errors[] = '男女別成績を持つ大会は、片方の性別だけを大会全体の公式結果として公開できません。男女を統合した最終成績を作成してください。';
        }

        $sourceSnapshots = $this->publicationSnapshots($tournament, $snapshot);
        $snapshotGroups = [];
        $unresolvedProfessionalRows = [];
        $duplicateIdentities = [];

        foreach ($sourceSnapshots as $sourceSnapshot) {
            $rows = $sourceSnapshot->rows()
                ->orderBy('ranking')
                ->orderBy('id')
                ->get()
                ->map(function ($row) use (&$unresolvedProfessionalRows): array {
                    $data = $row->toArray();
                    $resolvedBowler = $this->resolveBowler($data);

                    if ($resolvedBowler !== null) {
                        $data['pro_bowler_id'] = (int) $resolvedBowler->id;
                        $data['pro_bowler_license_no'] = $resolvedBowler->license_no;
                        $data['display_name'] = $resolvedBowler->name_kanji
                            ?: $resolvedBowler->name_kana
                            ?: $data['display_name'];
                    } elseif ($this->looksLikeProfessionalLicense($data['pro_bowler_license_no'] ?? null)) {
                        $unresolvedProfessionalRows[] = sprintf(
                            '%s（%s）',
                            (string) ($data['display_name'] ?? '氏名不明'),
                            (string) ($data['pro_bowler_license_no'] ?? '番号不明'),
                        );
                    }

                    return $data;
                })
                ->all();

            $identitiesInSnapshot = [];
            foreach ($rows as $row) {
                if (($row['subject_type'] ?? 'individual') === 'group') {
                    $errors[] = 'チーム・ダブルスの合算表は、個人ポイント・個人賞金・タイトルへ直接反映できません。個人別の配分規則を先に設定してください。';
                }
                if (array_key_exists('is_complete', $row) && $row['is_complete'] === false) {
                    $errors[] = sprintf(
                        '%sに未入力を含む行があります: %s',
                        $sourceSnapshot->result_name,
                        (string) ($row['display_name'] ?? '氏名不明'),
                    );
                }

                $identityKey = $this->calculator->identityKey($row);
                if ($identityKey === '') {
                    $errors[] = $sourceSnapshot->result_name.'に選手を特定できない行があります。';

                    continue;
                }
                if (isset($identitiesInSnapshot[$identityKey])) {
                    $duplicateIdentities[] = $sourceSnapshot->result_name.': '.(string) ($row['display_name'] ?? $identityKey);
                }
                $identitiesInSnapshot[$identityKey] = true;
            }

            $snapshotGroups[] = [
                'snapshot_id' => (int) $sourceSnapshot->id,
                'result_code' => (string) $sourceSnapshot->result_code,
                'rows' => $rows,
            ];
        }

        if ($unresolvedProfessionalRows !== []) {
            $errors[] = 'プロ選手マスタへ接続できない行があります: '.implode('、', array_slice(array_unique($unresolvedProfessionalRows), 0, 5));
        }
        if ($duplicateIdentities !== []) {
            $errors[] = '同じ選手が同一成績内に重複しています: '.implode('、', array_slice(array_unique($duplicateIdentities), 0, 5));
        }

        $completeness = $this->completenessService->audit($tournament, $sourceSnapshots, false);
        foreach ($completeness['errors'] as $completenessError) {
            $errors[] = '完全性検査: '.$completenessError;
        }

        $pointMap = $this->pointMap((int) $tournament->id);
        $prizeMap = $this->prizeMap((int) $tournament->id);
        $isSeasonTrial = $this->isSeasonTrial($tournament);
        $countsForPoints = (bool) $tournament->counts_for_official_points || $isSeasonTrial;
        $countsForPrize = (bool) $tournament->counts_for_prize;
        $selectedRows = $snapshotGroups[0]['rows'] ?? [];
        $hasSnapshotPoints = collect($selectedRows)->contains(
            fn (array $row): bool => (int) ($row['points'] ?? 0) > 0,
        );
        $hasSnapshotPrize = collect($selectedRows)->contains(
            fn (array $row): bool => (int) ($row['prize_money'] ?? 0) > 0,
        );

        if ($countsForPoints && ! $isSeasonTrial && $pointMap === [] && ! $hasSnapshotPoints) {
            $errors[] = '公式ポイント対象ですが、ポイント配分が未設定です。';
        } elseif ($countsForPoints && ! $isSeasonTrial && $pointMap === [] && $hasSnapshotPoints) {
            $warnings[] = 'ポイント配分がないため、最終成績スナップショットに保存済みのポイントを使用します。';
        }

        if ($countsForPrize && $prizeMap === [] && ! $hasSnapshotPrize) {
            $errors[] = '賞金対象ですが、賞金配分が未設定です。';
        } elseif ($countsForPrize && $prizeMap === [] && $hasSnapshotPrize) {
            $warnings[] = '賞金配分がないため、最終成績スナップショットに保存済みの賞金を使用します。';
        }

        $semifinalSnapshot = $sourceSnapshots->firstWhere('result_code', 'semifinal_total');
        $semifinalQualifierCount = $semifinalSnapshot?->rows()->count() ?? 0;

        $rows = $this->calculator->build(
            $snapshotGroups,
            $pointMap,
            $prizeMap,
            [
                'is_season_trial' => $isSeasonTrial,
                'counts_for_points' => $countsForPoints,
                'counts_for_prize' => $countsForPrize,
                'semifinal_qualifier_count' => $semifinalQualifierCount,
                'use_snapshot_points' => $pointMap === [] && $hasSnapshotPoints,
                'use_snapshot_prize' => $prizeMap === [] && $hasSnapshotPrize,
            ],
        );
        $rows = $this->completenessService->applyActualTotals($tournament, $rows);

        foreach ($rows as &$row) {
            $bowler = (int) ($row['pro_bowler_id'] ?? 0) > 0
                ? ProBowler::query()->find((int) $row['pro_bowler_id'])
                : null;
            $row['affiliation_display'] = $bowler === null
                ? null
                : $this->affiliationDisplay($bowler);
        }
        unset($row);

        if ($rows === []) {
            $errors[] = '公開できる成績行がありません。';
        }

        $sourceSnapshotIds = $sourceSnapshots->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $titleEligible = $this->titleSyncService->isEligibleTitleTournament($tournament);
        $distributionPayload = [
            'point_map' => $pointMap,
            'prize_map' => $prizeMap,
            'is_season_trial' => $isSeasonTrial,
            'season_trial_award_points' => $isSeasonTrial
                ? TournamentResultPublicationCalculator::SEASON_TRIAL_AWARD_POINTS
                : [],
            'semifinal_qualifier_count' => $semifinalQualifierCount,
            'counts_for_points' => $countsForPoints,
            'counts_for_prize' => $countsForPrize,
            'title_eligible' => $titleEligible,
            'title_scope' => (string) ($tournament->title_scope ?? ''),
            'title_category' => (string) ($tournament->title_category ?? ''),
            'competition_type' => (string) ($tournament->competition_type ?? ''),
        ];
        $distributionChecksum = $this->checksum($distributionPayload);
        $resultChecksum = $this->checksum([
            'tournament_id' => (int) $tournament->id,
            'snapshot_id' => (int) $snapshot->id,
            'source_snapshot_ids' => $sourceSnapshotIds,
            'distribution_checksum' => $distributionChecksum,
            'rows' => array_map(fn (array $row): array => [
                'ranking' => (int) $row['ranking'],
                'identity_key' => (string) $row['identity_key'],
                'pro_bowler_id' => (int) ($row['pro_bowler_id'] ?? 0),
                'pro_bowler_license_no' => (string) ($row['pro_bowler_license_no'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'affiliation_display' => (string) ($row['affiliation_display'] ?? ''),
                'total_pin' => (int) ($row['total_pin'] ?? 0),
                'games' => (int) ($row['games'] ?? 0),
                'points' => (int) ($row['points'] ?? 0),
                'award_points' => (int) ($row['award_points'] ?? 0),
                'step_points' => (int) ($row['step_points'] ?? 0),
                'prize_money' => (int) ($row['prize_money'] ?? 0),
                'source_snapshot_row_id' => $row['source_snapshot_row_id'] ?? null,
            ], $rows),
        ]);

        $proCount = count(array_filter($rows, fn (array $row): bool => (int) ($row['pro_bowler_id'] ?? 0) > 0));
        $summary = [
            'row_count' => count($rows),
            'pro_count' => $proCount,
            'amateur_count' => count($rows) - $proCount,
            'total_points' => array_sum(array_column($rows, 'points')),
            'total_prize_money' => array_sum(array_column($rows, 'prize_money')),
            'source_snapshot_count' => count($sourceSnapshotIds),
            'semifinal_qualifier_count' => $semifinalQualifierCount,
            'title_eligible' => $titleEligible,
        ];

        return [
            'can_publish' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'summary' => $summary,
            'rows' => $rows,
            'source_snapshots' => $sourceSnapshots,
            'source_snapshot_ids' => $sourceSnapshotIds,
            'distribution' => $distributionPayload,
            'distribution_checksum' => $distributionChecksum,
            'result_checksum' => $resultChecksum,
        ];
    }

    public function publish(
        Tournament $tournament,
        TournamentResultSnapshot $snapshot,
        int $publishedBy,
        string $expectedChecksum,
        ?string $notes = null,
    ): TournamentResultPublication {
        return DB::transaction(function () use ($tournament, $snapshot, $publishedBy, $expectedChecksum, $notes) {
            $lockedTournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            $lockedSnapshot = TournamentResultSnapshot::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $preview = $this->preview($lockedTournament, $lockedSnapshot);

            if (! $preview['can_publish']) {
                throw new InvalidArgumentException(implode(' ', $preview['errors']));
            }
            if (! hash_equals((string) $preview['result_checksum'], trim($expectedChecksum))) {
                throw new InvalidArgumentException('プレビュー後に成績または配分が変更されました。内容を再確認してください。');
            }

            $now = now();
            TournamentResultPublication::query()
                ->where('tournament_id', $lockedTournament->id)
                ->where('status', 'current')
                ->update([
                    'status' => 'superseded',
                    'superseded_at' => $now,
                    'updated_at' => $now,
                ]);

            $revision = (int) TournamentResultPublication::query()
                ->where('tournament_id', $lockedTournament->id)
                ->max('revision') + 1;

            $summary = $preview['summary'];
            $publication = TournamentResultPublication::query()->create([
                'tournament_id' => $lockedTournament->id,
                'snapshot_id' => $lockedSnapshot->id,
                'revision' => $revision,
                'status' => 'current',
                'row_count' => $summary['row_count'],
                'pro_count' => $summary['pro_count'],
                'amateur_count' => $summary['amateur_count'],
                'total_points' => $summary['total_points'],
                'total_prize_money' => $summary['total_prize_money'],
                'result_checksum' => $preview['result_checksum'],
                'distribution_checksum' => $preview['distribution_checksum'],
                'source_snapshot_ids' => $preview['source_snapshot_ids'],
                'validation_summary' => [
                    'warnings' => $preview['warnings'],
                    'summary' => $summary,
                    'distribution' => $preview['distribution'],
                ],
                'published_at' => $now,
                'published_by' => $publishedBy,
                'notes' => $this->nullableTrim($notes),
            ]);

            foreach ($preview['rows'] as $row) {
                TournamentResultPublicationRow::query()->create($this->publicationRowPayload($publication, $row));
            }

            TournamentResult::query()->where('tournament_id', $lockedTournament->id)->delete();
            foreach ($preview['rows'] as $row) {
                TournamentResult::query()->create($this->tournamentResultPayload($lockedTournament, $lockedSnapshot, $row));
            }

            TournamentResultSnapshot::query()
                ->where('tournament_id', $lockedTournament->id)
                ->where('is_published', true)
                ->update(['is_published' => false]);
            TournamentResultSnapshot::query()
                ->whereIn('id', $preview['source_snapshot_ids'])
                ->update(['is_published' => true]);

            $titleSummary = $this->titleSyncService->sync($lockedTournament);
            $publication->forceFill(['title_sync_summary' => $titleSummary])->save();

            return $publication->load(['snapshot', 'publishedBy', 'rows']);
        });
    }

    public function currentPublication(Tournament $tournament): ?TournamentResultPublication
    {
        return TournamentResultPublication::query()
            ->with(['snapshot', 'publishedBy'])
            ->where('tournament_id', $tournament->id)
            ->where('status', 'current')
            ->orderByDesc('revision')
            ->first();
    }

    /** @return Collection<int,TournamentResultSnapshot> */
    private function publicationSnapshots(Tournament $tournament, TournamentResultSnapshot $finalSnapshot): Collection
    {
        $gender = trim((string) ($finalSnapshot->gender ?? ''));
        $shift = trim((string) ($finalSnapshot->shift ?? ''));

        $supportingCodes = [
            'round_robin_total',
            'semifinal_total',
            'quarterfinal_total',
            'prelim_total',
        ];

        $supporting = TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->where('is_current', true)
            ->whereIn('result_code', $supportingCodes)
            ->when(
                $gender !== '',
                fn ($query) => $query->where('gender', $gender),
                fn ($query) => $query->whereNull('gender'),
            )
            ->when(
                $shift !== '',
                fn ($query) => $query->where('shift', $shift),
                fn ($query) => $query->whereNull('shift'),
            )
            ->orderByDesc('id')
            ->get()
            ->groupBy('result_code')
            ->map(fn (Collection $rows) => $rows->first());

        $ordered = collect([$finalSnapshot]);
        foreach ($supportingCodes as $code) {
            $candidate = $supporting->get($code);
            if ($candidate !== null && (int) $candidate->id !== (int) $finalSnapshot->id) {
                $ordered->push($candidate);
            }
        }

        return $ordered->values();
    }

    /** @return array<int,int> */
    private function pointMap(int $tournamentId): array
    {
        return DB::table('point_distributions')
            ->where('tournament_id', $tournamentId)
            ->orderBy('rank')
            ->pluck('points', 'rank')
            ->mapWithKeys(fn ($points, $rank): array => [(int) $rank => (int) $points])
            ->all();
    }

    /** @return array<int,int> */
    private function prizeMap(int $tournamentId): array
    {
        return DB::table('prize_distributions')
            ->where('tournament_id', $tournamentId)
            ->orderBy('rank')
            ->pluck('amount', 'rank')
            ->mapWithKeys(fn ($amount, $rank): array => [(int) $rank => (int) $amount])
            ->all();
    }

    /** @param array<string,mixed> $row */
    private function resolveBowler(array $row): ?ProBowler
    {
        $bowlerId = (int) ($row['pro_bowler_id'] ?? 0);
        if ($bowlerId > 0) {
            $bowler = ProBowler::query()->find($bowlerId);
            if ($bowler !== null) {
                return $bowler;
            }
        }

        $license = mb_strtoupper(preg_replace('/\s+/u', '', trim((string) ($row['pro_bowler_license_no'] ?? ''))) ?? '');
        if ($license === '' || $license === 'アマ' || str_starts_with($license, 'AMATEUR-')) {
            return null;
        }

        $exact = ProBowler::query()->whereRaw('upper(license_no) = ?', [$license])->first();
        if ($exact !== null) {
            return $exact;
        }

        $digits = preg_replace('/\D+/', '', $license) ?? '';
        if ($digits === '') {
            return null;
        }
        $last4 = str_pad(substr($digits, -4), 4, '0', STR_PAD_LEFT);
        $query = ProBowler::query()
            ->whereRaw("right(regexp_replace(upper(license_no), '[^0-9]', '', 'g'), 4) = ?", [$last4]);

        $gender = strtoupper(trim((string) ($row['gender'] ?? '')));
        if (in_array($gender, ['M', 'F'], true)) {
            $query->whereRaw('upper(left(license_no, 1)) = ?', [$gender]);
        }

        $matches = $query->orderBy('id')->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function looksLikeProfessionalLicense(mixed $license): bool
    {
        $license = mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $license)) ?? '');

        return $license !== ''
            && $license !== 'アマ'
            && ! str_starts_with($license, 'AMATEUR-');
    }

    private function isSeasonTrial(Tournament $tournament): bool
    {
        return (string) $tournament->title_scope === 'season_trial'
            || (string) $tournament->title_category === 'season_trial'
            || str_contains((string) $tournament->name, 'シーズントライアル');
    }

    private function affiliationDisplay(ProBowler $bowler): ?string
    {
        $values = collect([$bowler->organization_name, $bowler->equipment_contract])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        return $values->isEmpty() ? null : $values->implode('/');
    }

    /** @param array<string,mixed> $payload */
    private function checksum(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $row */
    private function publicationRowPayload(TournamentResultPublication $publication, array $row): array
    {
        $breakdown = is_array($row['breakdown'] ?? null) ? $row['breakdown'] : [];
        $breakdown['_publication_source'] = [
            'result_code' => $row['source_result_code'] ?? null,
            'snapshot_id' => $row['source_snapshot_id'] ?? null,
            'snapshot_row_id' => $row['source_snapshot_row_id'] ?? null,
        ];

        return [
            'publication_id' => $publication->id,
            'source_snapshot_id' => $row['source_snapshot_id'] ?? null,
            'source_snapshot_row_id' => $row['source_snapshot_row_id'] ?? null,
            'source_result_code' => $row['source_result_code'] ?? null,
            'ranking' => (int) $row['ranking'],
            'pro_bowler_id' => (int) ($row['pro_bowler_id'] ?? 0) ?: null,
            'amateur_bowler_id' => (int) ($row['amateur_bowler_id'] ?? 0) ?: null,
            'pro_bowler_license_no' => $this->nullableTrim($row['pro_bowler_license_no'] ?? null),
            'amateur_name' => (int) ($row['pro_bowler_id'] ?? 0) > 0
                ? null
                : $this->nullableTrim($row['amateur_name'] ?? $row['display_name'] ?? null),
            'display_name' => trim((string) ($row['display_name'] ?? $row['amateur_name'] ?? '参加者')),
            'identity_key' => (string) $row['identity_key'],
            'gender' => $this->nullableTrim($row['gender'] ?? null),
            'entry_number' => $this->nullableTrim($row['entry_number'] ?? null),
            'total_pin' => (int) ($row['total_pin'] ?? 0),
            'games' => (int) ($row['games'] ?? 0),
            'average' => isset($row['average']) ? (float) $row['average'] : null,
            'points' => (int) ($row['points'] ?? 0),
            'award_points' => (int) ($row['award_points'] ?? 0),
            'step_points' => (int) ($row['step_points'] ?? 0),
            'prize_money' => (int) ($row['prize_money'] ?? 0),
            'affiliation_display' => $this->nullableTrim($row['affiliation_display'] ?? null),
            'breakdown' => $breakdown,
        ];
    }

    /** @param array<string,mixed> $row */
    private function tournamentResultPayload(
        Tournament $tournament,
        TournamentResultSnapshot $snapshot,
        array $row,
    ): array {
        $license = $this->nullableTrim($row['pro_bowler_license_no'] ?? null)
            ?? $this->fallbackLicense($tournament, $snapshot, $row);
        $proBowlerId = (int) ($row['pro_bowler_id'] ?? 0) ?: null;

        return [
            'tournament_id' => $tournament->id,
            'pro_bowler_id' => $proBowlerId,
            'pro_bowler_license_no' => $license,
            'amateur_name' => $proBowlerId === null
                ? trim((string) ($row['amateur_name'] ?? $row['display_name'] ?? '参加者'))
                : null,
            'ranking' => (int) $row['ranking'],
            'points' => (int) ($row['points'] ?? 0),
            'award_points' => (int) ($row['award_points'] ?? 0),
            'step_points' => (int) ($row['step_points'] ?? 0),
            'total_pin' => (int) ($row['total_pin'] ?? 0),
            'games' => (int) ($row['games'] ?? 0),
            'average' => isset($row['average']) ? round((float) $row['average'], 2) : null,
            'prize_money' => (int) ($row['prize_money'] ?? 0),
            'ranking_year' => $this->rankingYear($tournament),
            'affiliation_display' => $this->nullableTrim($row['affiliation_display'] ?? null),
        ];
    }

    /** @param array<string,mixed> $row */
    private function fallbackLicense(Tournament $tournament, TournamentResultSnapshot $snapshot, array $row): string
    {
        $hash = substr(hash('sha256', implode('|', [
            (string) $tournament->id,
            (string) $snapshot->id,
            (string) ($row['ranking'] ?? 0),
            (string) ($row['identity_key'] ?? ''),
        ])), 0, 10);

        return sprintf('AMATEUR-%d-%03d-%s', $tournament->id, (int) ($row['ranking'] ?? 0), $hash);
    }

    private function rankingYear(Tournament $tournament): int
    {
        if ((int) $tournament->year > 0) {
            return (int) $tournament->year;
        }

        foreach ([$tournament->start_date, $tournament->end_date] as $date) {
            if ($date !== null && $date !== '') {
                return Carbon::parse($date)->year;
            }
        }

        return (int) now()->year;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
