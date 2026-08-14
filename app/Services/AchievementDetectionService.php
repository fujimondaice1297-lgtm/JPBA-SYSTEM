<?php

namespace App\Services;

use App\Models\GameScore;
use App\Models\ProBowler;
use App\Models\RecordType;
use App\Models\ScoreSeriesDefinition;
use App\Models\StageSetting;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class AchievementDetectionService
{
    public function canDetect(): bool
    {
        return Schema::hasTable('record_types')
            && Schema::hasTable('score_series_definitions')
            && Schema::hasColumn('record_types', 'detection_key');
    }

    public function scanGameScore(GameScore $score): void
    {
        if (! $this->canDetect()) {
            return;
        }

        $proBowler = $this->resolveProBowler($score);
        if (! $proBowler) {
            return;
        }

        $tournament = Tournament::query()->find($score->tournament_id);
        if (! $tournament) {
            return;
        }

        $this->scanPerfect($score, $proBowler, $tournament);

        foreach ($this->seriesDefinitionsForScore($score) as $definition) {
            $this->scanSeriesDefinition(
                $definition,
                $proBowler,
                $this->nullableString($score->shift),
                $this->genderFor($score, $proBowler)
            );
        }
    }

    public function handleDeletedGameScore(GameScore $score): void
    {
        if (! $this->canDetect()) {
            return;
        }

        $this->invalidateCandidate(
            'score:perfect:' . $score->id,
            '元スコアが削除されました。'
        );

        $proBowler = $this->resolveProBowler($score);
        if (! $proBowler) {
            return;
        }

        foreach ($this->seriesDefinitionsForScore($score) as $definition) {
            $this->scanSeriesDefinition(
                $definition,
                $proBowler,
                $this->nullableString($score->shift),
                $this->genderFor($score, $proBowler)
            );
        }
    }

    public function scanTournament(int $tournamentId): array
    {
        $summary = [
            'perfect_candidates' => 0,
            'eight_hundred_candidates' => 0,
        ];

        if (! $this->canDetect()) {
            return $summary;
        }

        RecordType::query()
            ->where('tournament_id', $tournamentId)
            ->where('record_type', 'perfect')
            ->where('source_type', 'score_auto')
            ->with('sourceGameScore')
            ->each(function (RecordType $record): void {
                if ($record->sourceGameScore) {
                    $this->scanGameScore($record->sourceGameScore);

                    return;
                }

                $this->invalidateCandidate(
                    (string) $record->detection_key,
                    '根拠となるスコアが削除されています。'
                );
            });

        GameScore::query()
            ->where('tournament_id', $tournamentId)
            ->whereNotNull('pro_bowler_id')
            ->where('score', 300)
            ->orderBy('id')
            ->each(function (GameScore $score) use (&$summary): void {
                $before = RecordType::query()
                    ->where('detection_key', 'score:perfect:' . $score->id)
                    ->exists();
                $this->scanGameScore($score);
                if (! $before) {
                    $summary['perfect_candidates']++;
                }
            });

        StageSetting::query()
            ->where('tournament_id', $tournamentId)
            ->where('enabled', true)
            ->where('total_games', 3)
            ->each(function (StageSetting $setting): void {
                ScoreSeriesDefinition::query()->firstOrCreate(
                    [
                        'tournament_id' => $setting->tournament_id,
                        'stage' => $setting->stage,
                        'shift' => null,
                        'gender' => null,
                        'start_game' => 1,
                        'end_game' => 3,
                    ],
                    [
                        'label' => $setting->stage . ' 3Gシリーズ',
                        'is_800_eligible' => true,
                        'is_enabled' => true,
                        'source' => 'stage_setting_auto',
                    ]
                );
            });

        $definitions = ScoreSeriesDefinition::query()
            ->where('tournament_id', $tournamentId)
            ->where('is_enabled', true)
            ->where('is_800_eligible', true)
            ->get();

        foreach ($definitions as $definition) {
            RecordType::query()
                ->where('score_series_definition_id', $definition->id)
                ->with('proBowler')
                ->each(function (RecordType $record) use ($definition): void {
                    if (! $record->proBowler) {
                        return;
                    }
                    $this->scanSeriesDefinition(
                        $definition,
                        $record->proBowler,
                        $this->nullableString($record->shift),
                        $this->nullableString($record->gender)
                    );
                });

            $players = GameScore::query()
                ->where('tournament_id', $tournamentId)
                ->where('stage', $definition->stage)
                ->whereBetween('game_number', [$definition->start_game, $definition->end_game])
                ->whereNotNull('pro_bowler_id')
                ->when(
                    $definition->shift !== null,
                    fn (Builder $query) => $query->where('shift', $definition->shift)
                )
                ->when(
                    $definition->gender !== null,
                    fn (Builder $query) => $query->where('gender', $definition->gender)
                )
                ->get(['pro_bowler_id', 'shift', 'gender'])
                ->unique(fn (GameScore $row) => implode('|', [
                    $row->pro_bowler_id,
                    $row->shift ?? '',
                    $row->gender ?? '',
                ]));

            foreach ($players as $row) {
                $bowler = ProBowler::query()->find($row->pro_bowler_id);
                if (! $bowler) {
                    continue;
                }

                $key = $this->seriesDetectionKey(
                    $definition,
                    $bowler->id,
                    $this->nullableString($row->shift),
                    $this->genderFor($row, $bowler)
                );
                $before = RecordType::query()->where('detection_key', $key)->exists();
                $this->scanSeriesDefinition(
                    $definition,
                    $bowler,
                    $this->nullableString($row->shift),
                    $this->genderFor($row, $bowler)
                );
                if (! $before && RecordType::query()->where('detection_key', $key)->exists()) {
                    $summary['eight_hundred_candidates']++;
                }
            }
        }

        return $summary;
    }

    public function reconcileSeriesDefinition(ScoreSeriesDefinition $definition): void
    {
        if (! $this->canDetect()) {
            return;
        }

        RecordType::query()
            ->where('score_series_definition_id', $definition->id)
            ->with('proBowler')
            ->each(function (RecordType $record) use ($definition): void {
                if (! $record->proBowler) {
                    return;
                }

                $this->scanSeriesDefinition(
                    $definition,
                    $record->proBowler,
                    $this->nullableString($record->shift),
                    $this->nullableString($record->gender)
                );
            });

        if ($definition->is_enabled && $definition->is_800_eligible) {
            $this->scanTournament((int) $definition->tournament_id);
        }
    }

    private function scanPerfect(GameScore $score, ProBowler $bowler, Tournament $tournament): void
    {
        $detectionKey = 'score:perfect:' . $score->id;
        if ((int) $score->score !== 300) {
            $this->invalidateCandidate($detectionKey, 'スコアが300ではなくなりました。');

            return;
        }

        $this->upsertCandidate($detectionKey, [
            'record_type' => 'perfect',
            'pro_bowler_id' => $bowler->id,
            'tournament_id' => $tournament->id,
            'source_game_score_id' => $score->id,
            'tournament_name' => $tournament->name,
            'stage' => $score->stage,
            'shift' => $this->nullableString($score->shift),
            'gender' => $this->genderFor($score, $bowler),
            'game_numbers' => trim($score->stage . ' ' . $score->game_number . 'G目'),
            'awarded_on' => $tournament->start_date?->format('Y-m-d'),
            'registration_mode' => $this->registrationModeFor($tournament),
            'source_type' => 'score_auto',
            'source_label' => '成績入力から自動検出',
            'evidence_text' => sprintf(
                '%s / %s / %sG / %d',
                $tournament->name,
                $score->stage,
                $score->game_number,
                $score->score
            ),
            'warning' => null,
            'detected_at' => now(),
        ]);
    }

    private function scanSeriesDefinition(
        ScoreSeriesDefinition $definition,
        ProBowler $bowler,
        ?string $shift,
        ?string $gender
    ): void {
        $detectionKey = $this->seriesDetectionKey($definition, $bowler->id, $shift, $gender);
        if ($definition->game_count !== 3 || ! $definition->is_enabled || ! $definition->is_800_eligible) {
            $this->invalidateCandidate(
                $detectionKey,
                'この3ゲームシリーズ設定は現在無効です。'
            );

            return;
        }

        $query = GameScore::query()
            ->where('tournament_id', $definition->tournament_id)
            ->where('stage', $definition->stage)
            ->where('pro_bowler_id', $bowler->id)
            ->whereBetween('game_number', [$definition->start_game, $definition->end_game]);

        $this->applyNullableMatch($query, 'shift', $definition->shift ?? $shift);
        $this->applyNullableMatch($query, 'gender', $definition->gender ?? $gender);

        $scores = $query
            ->orderBy('game_number')
            ->get(['id', 'game_number', 'score'])
            ->unique('game_number')
            ->values();

        $expectedGames = range((int) $definition->start_game, (int) $definition->end_game);
        $actualGames = $scores->pluck('game_number')->map(fn ($value) => (int) $value)->all();
        $total = (int) $scores->sum('score');

        if ($actualGames !== $expectedGames || $scores->count() !== 3 || $total < 800) {
            $this->invalidateCandidate(
                $detectionKey,
                $scores->count() !== 3
                    ? '対象3ゲームが揃っていません。'
                    : '対象3ゲームの合計が800未満になりました。'
            );

            return;
        }

        $tournament = Tournament::query()->find($definition->tournament_id);
        if (! $tournament) {
            return;
        }

        $scoreValues = $scores->map(fn (GameScore $score) => [
            'game_number' => (int) $score->game_number,
            'score' => (int) $score->score,
            'game_score_id' => (int) $score->id,
        ])->all();

        $this->upsertCandidate($detectionKey, [
            'record_type' => 'eight_hundred',
            'pro_bowler_id' => $bowler->id,
            'tournament_id' => $tournament->id,
            'score_series_definition_id' => $definition->id,
            'tournament_name' => $tournament->name,
            'stage' => $definition->stage,
            'shift' => $definition->shift ?? $shift,
            'gender' => $definition->gender ?? $gender,
            'series_label' => $definition->label,
            'series_start_game' => $definition->start_game,
            'series_end_game' => $definition->end_game,
            'series_total' => $total,
            'series_scores' => $scoreValues,
            'game_numbers' => sprintf(
                '%s（%dG～%dG）',
                $definition->label,
                $definition->start_game,
                $definition->end_game
            ),
            'awarded_on' => $tournament->start_date?->format('Y-m-d'),
            'registration_mode' => $this->registrationModeFor($tournament),
            'source_type' => 'score_auto',
            'source_label' => '成績入力から自動検出',
            'evidence_text' => implode('・', array_column($scoreValues, 'score')) . '＝' . $total,
            'warning' => null,
            'detected_at' => now(),
        ]);
    }

    private function seriesDefinitionsForScore(GameScore $score)
    {
        $query = ScoreSeriesDefinition::query()
            ->where('tournament_id', $score->tournament_id)
            ->where('stage', $score->stage)
            ->where('is_enabled', true)
            ->where('is_800_eligible', true)
            ->where('start_game', '<=', $score->game_number)
            ->where('end_game', '>=', $score->game_number);

        $query->where(function (Builder $query) use ($score): void {
            $query->whereNull('shift');
            if ($this->nullableString($score->shift) !== null) {
                $query->orWhere('shift', $score->shift);
            }
        });
        $query->where(function (Builder $query) use ($score): void {
            $query->whereNull('gender');
            if ($this->nullableString($score->gender) !== null) {
                $query->orWhere('gender', $score->gender);
            }
        });

        $definitions = $query->get();
        if ($definitions->isNotEmpty()) {
            return $definitions;
        }

        $stageSetting = StageSetting::query()
            ->where('tournament_id', $score->tournament_id)
            ->where('stage', $score->stage)
            ->where('enabled', true)
            ->where('total_games', 3)
            ->first();

        if (! $stageSetting) {
            return collect();
        }

        return collect([
            ScoreSeriesDefinition::query()->firstOrCreate(
                [
                    'tournament_id' => $score->tournament_id,
                    'stage' => $score->stage,
                    'shift' => null,
                    'gender' => null,
                    'start_game' => 1,
                    'end_game' => 3,
                ],
                [
                    'label' => $score->stage . ' 3Gシリーズ',
                    'is_800_eligible' => true,
                    'is_enabled' => true,
                    'source' => 'stage_setting_auto',
                ]
            ),
        ]);
    }

    private function upsertCandidate(string $detectionKey, array $attributes): RecordType
    {
        $record = RecordType::query()->where('detection_key', $detectionKey)->first();
        if (! $record) {
            return RecordType::query()->create(array_merge($attributes, [
                'detection_key' => $detectionKey,
                'status' => RecordType::STATUS_CANDIDATE,
            ]));
        }

        $protectedStatus = in_array(
            $record->status,
            [RecordType::STATUS_CONFIRMED, RecordType::STATUS_REJECTED],
            true
        );
        $record->fill($attributes);
        if (! $protectedStatus) {
            $record->status = RecordType::STATUS_CANDIDATE;
        }
        $record->save();

        return $record;
    }

    private function invalidateCandidate(string $detectionKey, string $warning): void
    {
        $record = RecordType::query()->where('detection_key', $detectionKey)->first();
        if (! $record) {
            return;
        }

        if ($record->status === RecordType::STATUS_CONFIRMED) {
            $record->update(['warning' => $warning]);

            return;
        }

        if ($record->status === RecordType::STATUS_CANDIDATE) {
            $record->update([
                'status' => RecordType::STATUS_VOID,
                'warning' => $warning,
            ]);
        }
    }

    private function seriesDetectionKey(
        ScoreSeriesDefinition $definition,
        int $proBowlerId,
        ?string $shift,
        ?string $gender
    ): string {
        return implode(':', [
            'score',
            'eight_hundred',
            $definition->id,
            $proBowlerId,
            $shift ?: '-',
            $gender ?: '-',
        ]);
    }

    private function resolveProBowler(GameScore $score): ?ProBowler
    {
        if ($score->pro_bowler_id) {
            return ProBowler::query()->find($score->pro_bowler_id);
        }

        $licenseNo = strtoupper(trim((string) $score->license_number));
        if ($licenseNo === '') {
            return null;
        }

        return ProBowler::query()->where('license_no', $licenseNo)->first();
    }

    private function genderFor(GameScore $score, ProBowler $bowler): ?string
    {
        $gender = strtoupper(trim((string) $score->gender));
        if (in_array($gender, ['M', 'F'], true)) {
            return $gender;
        }

        $prefix = strtoupper(substr((string) $bowler->license_no, 0, 1));

        return in_array($prefix, ['M', 'F'], true) ? $prefix : null;
    }

    private function registrationModeFor(Tournament $tournament): string
    {
        $cutoverDate = config('achievements.cutover_date');
        if (! $cutoverDate || ! $tournament->start_date) {
            return RecordType::MODE_HISTORICAL;
        }

        return $tournament->start_date->format('Y-m-d') >= $cutoverDate
            ? RecordType::MODE_NEW
            : RecordType::MODE_HISTORICAL;
    }

    private function applyNullableMatch(Builder $query, string $column, ?string $value): void
    {
        if ($value === null) {
            $query->where(function (Builder $query) use ($column): void {
                $query->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        $query->where($column, $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
