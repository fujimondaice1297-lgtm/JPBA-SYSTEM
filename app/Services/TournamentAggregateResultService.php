<?php

namespace App\Services;

use App\Models\TournamentAggregateDefinition;
use App\Models\TournamentCompetitorGroup;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TournamentAggregateResultService
{
    public function __construct(
        private readonly TournamentAggregateCalculator $calculator,
    ) {}

    public function calculate(
        TournamentAggregateDefinition $definition,
        ?int $reflectedBy = null,
    ): TournamentResultSnapshot {
        $definition->load(['tournament', 'sources.sourceTournament']);
        $sources = $definition->sources->values();

        if ($sources->isEmpty()) {
            throw new InvalidArgumentException('合算元の競技を1件以上登録してください。');
        }

        if (! in_array($definition->subject_type, ['individual', 'group'], true)) {
            throw new InvalidArgumentException('合算対象は individual / group のみ対応しています。');
        }

        if ($definition->subject_type === 'group') {
            $invalidSource = $sources->first(
                fn ($source) => (int) $source->source_tournament_id !== (int) $definition->tournament_id
            );
            if ($invalidSource) {
                throw new InvalidArgumentException('チーム合算では、この大会自身のスコアだけを合算元に指定してください。');
            }
        }

        [$subjects, $diagnostics] = $definition->subject_type === 'group'
            ? $this->collectGroupSubjects($definition, $sources->all())
            : $this->collectIndividualSubjects($definition, $sources->all());

        $sourceDefinitions = $sources->map(fn ($source): array => [
            'id' => (int) $source->id,
            'label' => $source->label,
            'source_tournament_id' => (int) $source->source_tournament_id,
            'stage' => $source->stage,
            'game_from' => $source->game_from,
            'game_to' => $source->game_to,
            'expected_games_per_member' => $source->expected_games_per_member,
            'is_required' => (bool) $source->is_required,
        ])->all();

        $rankedRows = $this->calculator->finalize(
            $subjects,
            $sourceDefinitions,
            $definition->subject_type,
            (bool) $definition->require_all_sources,
            $definition->tie_break_policy ?: 'shared_rank',
        );

        $gamesCount = $this->expectedGamesCount($definition, $sourceDefinitions);

        return DB::transaction(function () use (
            $definition,
            $sourceDefinitions,
            $rankedRows,
            $diagnostics,
            $gamesCount,
            $reflectedBy,
        ) {
            TournamentResultSnapshot::query()
                ->where('aggregate_definition_id', $definition->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $snapshot = TournamentResultSnapshot::query()->create([
                'tournament_id' => $definition->tournament_id,
                'aggregate_definition_id' => $definition->id,
                'result_code' => 'aggregate:'.$definition->code,
                'result_name' => $definition->name,
                'result_type' => $definition->subject_type === 'group'
                    ? 'team_total_pin'
                    : 'all_events',
                'stage_name' => '合算成績',
                'gender' => $definition->gender,
                'shift' => null,
                'games_count' => $gamesCount,
                'carry_game_count' => 0,
                'carry_stage_names' => [],
                'calculation_definition' => [
                    'aggregate_definition_id' => $definition->id,
                    'subject_type' => $definition->subject_type,
                    'tie_break_policy' => $definition->tie_break_policy ?: 'shared_rank',
                    'require_all_sources' => (bool) $definition->require_all_sources,
                    'sources' => $sourceDefinitions,
                    'diagnostics' => $diagnostics,
                ],
                'reflected_at' => now(),
                'reflected_by' => $reflectedBy,
                'is_final' => false,
                'is_published' => (bool) $definition->is_published,
                'is_current' => true,
                'notes' => $definition->notes,
            ]);

            foreach ($rankedRows as $row) {
                $breakdown = [
                    'sources' => array_values($row['source_breakdown'] ?? []),
                    'incomplete_reasons' => $row['incomplete_reasons'] ?? [],
                    'identity_verified' => (bool) ($row['identity_verified'] ?? true),
                ];

                TournamentResultSnapshotRow::query()->create([
                    'snapshot_id' => $snapshot->id,
                    'ranking' => $row['ranking'],
                    'subject_type' => $definition->subject_type,
                    'competitor_group_id' => $row['competitor_group_id'] ?? null,
                    'pro_bowler_id' => $row['pro_bowler_id'] ?? null,
                    'amateur_bowler_id' => $row['amateur_bowler_id'] ?? null,
                    'pro_bowler_license_no' => $row['pro_bowler_license_no'] ?? null,
                    'amateur_name' => $row['amateur_name'] ?? null,
                    'display_name' => $row['display_name'],
                    'gender' => $row['gender'] ?? null,
                    'shift' => null,
                    'entry_number' => $row['entry_number'] ?? null,
                    'identity_key' => $row['identity_key'],
                    'scratch_pin' => $row['total_pin'],
                    'carry_pin' => 0,
                    'total_pin' => $row['total_pin'],
                    'games' => $row['games'],
                    'source_count' => $row['source_count'],
                    'is_complete' => $row['is_complete'],
                    'breakdown' => $breakdown,
                    'average' => $row['average'],
                    'tie_break_value' => $row['tie_break_value'],
                    'points' => null,
                    'prize_money' => null,
                ]);
            }

            return $snapshot->load(['rows', 'aggregateDefinition.sources.sourceTournament']);
        });
    }

    private function collectIndividualSubjects(TournamentAggregateDefinition $definition, array $sources): array
    {
        $subjects = [];
        $seenScoreIds = [];
        $diagnostics = [
            'score_rows' => 0,
            'unverified_identity_rows' => 0,
        ];

        foreach ($sources as $source) {
            foreach ($this->scoreRowsForSource($source, $definition->gender) as $row) {
                $this->guardAgainstOverlap($seenScoreIds, (int) $row->id);
                $identity = $this->individualIdentity($row);
                if ($identity === null) {
                    continue;
                }

                $key = $identity['identity_key'];
                if (! isset($subjects[$key])) {
                    $subjects[$key] = array_merge($identity, [
                        'competitor_group_id' => null,
                        'total_pin' => 0,
                        'games' => 0,
                        'score_values' => [],
                        'source_breakdown' => [],
                    ]);
                }

                $this->addScore($subjects[$key], $source, $row, $key);
                $diagnostics['score_rows']++;
                if (! $identity['identity_verified']) {
                    $diagnostics['unverified_identity_rows']++;
                }
            }
        }

        return [array_values($subjects), $diagnostics];
    }

    private function collectGroupSubjects(TournamentAggregateDefinition $definition, array $sources): array
    {
        $groups = TournamentCompetitorGroup::query()
            ->where('tournament_id', $definition->tournament_id)
            ->where('is_active', true)
            ->with(['members.participant'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($groups->isEmpty()) {
            throw new InvalidArgumentException('ダブルス／チームの編成を先に登録してください。');
        }

        $subjects = [];
        $lookups = [
            'participant' => [],
            'pro' => [],
            'amateur' => [],
            'license' => [],
        ];

        foreach ($groups as $group) {
            $key = 'group:'.$group->id;
            $memberKeys = [];
            foreach ($group->members as $member) {
                $participant = $member->participant;
                if (! $participant) {
                    continue;
                }

                $memberKey = 'participant:'.$participant->id;
                $memberKeys[] = $memberKey;
                $this->registerLookup($lookups['participant'], (string) $participant->id, [$key, $memberKey]);
                $this->registerLookup($lookups['pro'], (string) ($participant->pro_bowler_id ?? ''), [$key, $memberKey]);
                $this->registerLookup($lookups['amateur'], (string) ($participant->amateur_bowler_id ?? ''), [$key, $memberKey]);
                $this->registerLookup(
                    $lookups['license'],
                    $this->normalizeLicense($participant->pro_bowler_license_no ?? $participant->display_license_no),
                    [$key, $memberKey]
                );
            }

            $subjects[$key] = [
                'identity_key' => $key,
                'competitor_group_id' => $group->id,
                'pro_bowler_id' => null,
                'amateur_bowler_id' => null,
                'pro_bowler_license_no' => null,
                'amateur_name' => null,
                'display_name' => $group->name,
                'gender' => $definition->gender,
                'entry_number' => $group->code,
                'identity_verified' => true,
                'expected_member_count' => $group->expected_member_count,
                'member_keys' => $memberKeys,
                'total_pin' => 0,
                'games' => 0,
                'score_values' => [],
                'source_breakdown' => [],
            ];
        }

        $seenScoreIds = [];
        $diagnostics = [
            'score_rows' => 0,
            'unassigned_score_rows' => 0,
            'unassigned_score_ids' => [],
        ];

        foreach ($sources as $source) {
            foreach ($this->scoreRowsForSource($source, $definition->gender) as $row) {
                $this->guardAgainstOverlap($seenScoreIds, (int) $row->id);
                $matched = $this->matchGroupMember($row, $lookups);
                if ($matched === null) {
                    $diagnostics['unassigned_score_rows']++;
                    if (count($diagnostics['unassigned_score_ids']) < 20) {
                        $diagnostics['unassigned_score_ids'][] = (int) $row->id;
                    }

                    continue;
                }

                [$groupKey, $memberKey] = $matched;
                $this->addScore($subjects[$groupKey], $source, $row, $memberKey);
                $diagnostics['score_rows']++;
            }
        }

        return [array_values($subjects), $diagnostics];
    }

    private function scoreRowsForSource(object $source, ?string $gender)
    {
        return DB::table('game_scores as g')
            ->leftJoin('tournament_participants as tp', 'tp.id', '=', 'g.tournament_participant_id')
            ->where('g.tournament_id', $source->source_tournament_id)
            ->when($source->stage, fn ($query) => $query->where('g.stage', $source->stage))
            ->when($source->game_from, fn ($query) => $query->where('g.game_number', '>=', $source->game_from))
            ->when($source->game_to, fn ($query) => $query->where('g.game_number', '<=', $source->game_to))
            ->when($gender, function ($query) use ($gender) {
                $query->where(function ($genderQuery) use ($gender) {
                    $genderQuery->where('g.gender', $gender)
                        ->orWhere(function ($fallback) use ($gender) {
                            $fallback->whereNull('g.gender')->where('tp.gender', $gender);
                        });
                });
            })
            ->select([
                'g.id',
                'g.score',
                'g.game_number',
                'g.stage',
                'g.tournament_participant_id',
                'g.pro_bowler_id as score_pro_bowler_id',
                'g.license_number',
                'g.name as score_name',
                'g.entry_number',
                'g.gender as score_gender',
                'tp.pro_bowler_id as participant_pro_bowler_id',
                'tp.amateur_bowler_id',
                'tp.pro_bowler_license_no as participant_license_no',
                'tp.display_license_no',
                'tp.display_name as participant_display_name',
                'tp.participant_type',
                'tp.gender as participant_gender',
            ])
            ->orderBy('g.id')
            ->get();
    }

    private function individualIdentity(object $row): ?array
    {
        $proBowlerId = (int) ($row->participant_pro_bowler_id ?: $row->score_pro_bowler_id ?: 0);
        $amateurBowlerId = (int) ($row->amateur_bowler_id ?: 0);
        $displayName = trim((string) ($row->participant_display_name ?: $row->score_name ?: ''));
        $license = $this->firstUsableLicense([
            $row->participant_license_no,
            $row->display_license_no,
            $row->license_number,
        ]);
        $gender = trim((string) ($row->score_gender ?: $row->participant_gender ?: '')) ?: null;

        if ($proBowlerId > 0) {
            return [
                'identity_key' => 'pro:'.$proBowlerId,
                'pro_bowler_id' => $proBowlerId,
                'amateur_bowler_id' => null,
                'pro_bowler_license_no' => $license,
                'amateur_name' => null,
                'display_name' => $displayName ?: ('プロ #'.$proBowlerId),
                'gender' => $gender,
                'entry_number' => $row->entry_number,
                'identity_verified' => true,
            ];
        }

        if ($amateurBowlerId > 0) {
            return [
                'identity_key' => 'amateur:'.$amateurBowlerId,
                'pro_bowler_id' => null,
                'amateur_bowler_id' => $amateurBowlerId,
                'pro_bowler_license_no' => null,
                'amateur_name' => $displayName ?: ('アマチュア #'.$amateurBowlerId),
                'display_name' => $displayName ?: ('アマチュア #'.$amateurBowlerId),
                'gender' => $gender,
                'entry_number' => $row->entry_number,
                'identity_verified' => true,
            ];
        }

        if ($license !== null) {
            return [
                'identity_key' => 'license:'.$this->normalizeLicense($license),
                'pro_bowler_id' => null,
                'amateur_bowler_id' => null,
                'pro_bowler_license_no' => $license,
                'amateur_name' => null,
                'display_name' => $displayName ?: $license,
                'gender' => $gender,
                'entry_number' => $row->entry_number,
                'identity_verified' => false,
            ];
        }

        if ($displayName === '') {
            return null;
        }

        return [
            'identity_key' => 'name:'.$this->normalizeName($displayName).':'.($gender ?? 'X'),
            'pro_bowler_id' => null,
            'amateur_bowler_id' => null,
            'pro_bowler_license_no' => null,
            'amateur_name' => $displayName,
            'display_name' => $displayName,
            'gender' => $gender,
            'entry_number' => $row->entry_number,
            'identity_verified' => false,
        ];
    }

    private function addScore(array &$subject, object $source, object $row, string $memberKey): void
    {
        $sourceId = (int) $source->id;
        if (! isset($subject['source_breakdown'][$sourceId])) {
            $subject['source_breakdown'][$sourceId] = [
                'source_id' => $sourceId,
                'source_tournament_id' => (int) $source->source_tournament_id,
                'label' => $source->label,
                'stage' => $source->stage,
                'total_pin' => 0,
                'games' => 0,
                'member_games' => [],
            ];
        }

        $score = (int) $row->score;
        $subject['total_pin'] += $score;
        $subject['games']++;
        $subject['score_values'][] = $score;
        $subject['source_breakdown'][$sourceId]['total_pin'] += $score;
        $subject['source_breakdown'][$sourceId]['games']++;
        $subject['source_breakdown'][$sourceId]['member_games'][$memberKey]
            = (int) ($subject['source_breakdown'][$sourceId]['member_games'][$memberKey] ?? 0) + 1;
    }

    private function matchGroupMember(object $row, array $lookups): ?array
    {
        $candidates = [
            ['participant', (string) ($row->tournament_participant_id ?? '')],
            ['pro', (string) ($row->participant_pro_bowler_id ?: $row->score_pro_bowler_id ?: '')],
            ['amateur', (string) ($row->amateur_bowler_id ?? '')],
            ['license', $this->firstUsableLicense([
                $row->participant_license_no,
                $row->display_license_no,
                $row->license_number,
            ])],
        ];

        foreach ($candidates as [$type, $value]) {
            $key = $type === 'license' ? $this->normalizeLicense($value) : trim((string) $value);
            if ($key !== '' && isset($lookups[$type][$key]) && $lookups[$type][$key] !== null) {
                return $lookups[$type][$key];
            }
        }

        return null;
    }

    private function registerLookup(array &$lookup, string $key, array $value): void
    {
        $key = trim($key);
        if ($key === '') {
            return;
        }

        if (array_key_exists($key, $lookup) && $lookup[$key] !== $value) {
            $lookup[$key] = null;

            return;
        }

        $lookup[$key] = $value;
    }

    private function guardAgainstOverlap(array &$seenScoreIds, int $scoreId): void
    {
        if (isset($seenScoreIds[$scoreId])) {
            throw new InvalidArgumentException(
                '合算元の条件が重複し、同じスコアが2回選択されています。game_scores #'.$scoreId
            );
        }

        $seenScoreIds[$scoreId] = true;
    }

    private function expectedGamesCount(TournamentAggregateDefinition $definition, array $sources): int
    {
        $games = array_sum(array_map(function (array $source) use ($definition): int {
            if (! $definition->require_all_sources && empty($source['is_required'])) {
                return 0;
            }

            $expected = (int) ($source['expected_games_per_member'] ?? 0);
            if ($expected > 0) {
                return $expected;
            }

            $from = (int) ($source['game_from'] ?? 0);
            $to = (int) ($source['game_to'] ?? 0);

            return $from > 0 && $to >= $from ? ($to - $from + 1) : 0;
        }, $sources));

        return $games;
    }

    private function firstUsableLicense(array $values): ?string
    {
        foreach ($values as $value) {
            $license = trim((string) $value);
            if ($license !== '' && ! in_array(mb_strtoupper($license), ['アマ', 'AMA'], true)) {
                return $license;
            }
        }

        return null;
    }

    private function normalizeLicense(mixed $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $value)) ?? '');
    }

    private function normalizeName(string $value): string
    {
        return mb_strtolower(preg_replace('/[\s　]+/u', '', trim($value)) ?? '');
    }
}
