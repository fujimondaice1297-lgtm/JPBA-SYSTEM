<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Models\RecordType;
use App\Services\AchievementRecordService;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

class ResolveOfficialPerfectCandidatesCommand extends Command
{
    protected $signature = 'jpba:resolve-official-perfect-candidates
        {--force : Write the official corrections. Without this option, run as a dry-run}
        {--confirm : Confirm each safely matched candidate}
        {--json : Output a machine-readable report}';

    protected $description = 'Resolve score-detected perfect candidates from verified JPBA official sources.';

    public function handle(AchievementRecordService $records): int
    {
        $force = (bool) $this->option('force');
        $confirm = (bool) $this->option('confirm');
        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'dataset_rows' => 0,
            'records_matched' => 0,
            'candidate_records' => 0,
            'already_confirmed' => 0,
            'duplicate_candidates' => 0,
            'already_void' => 0,
            'records_updated' => 0,
            'canonical_records_updated' => 0,
            'records_confirmed' => 0,
            'candidates_voided' => 0,
            'authoritative_totals_reconciled' => 0,
            'perfect_count_increase' => 0,
            'records_missing' => 0,
            'identity_mismatches' => 0,
            'certification_conflicts' => 0,
            'invalid_statuses' => 0,
            'errors' => [],
        ];
        $verifiedMinimums = [];

        foreach ($this->dataset() as $item) {
            $report['dataset_rows']++;
            $record = RecordType::query()
                ->with('proBowler:id,license_no,name_kanji')
                ->find($item['record_id']);

            if (! $record) {
                $report['records_missing']++;
                $report['errors'][] = [
                    'record_id' => $item['record_id'],
                    'error' => 'record_missing',
                ];
                continue;
            }

            $identityErrors = $this->identityErrors($record, $item);
            if ($identityErrors !== []) {
                $report['identity_mismatches']++;
                $report['errors'][] = [
                    'record_id' => $item['record_id'],
                    'error' => 'identity_mismatch',
                    'details' => $identityErrors,
                ];
                continue;
            }

            $canonicalId = $item['canonical_record_id'] ?? null;
            $allowedStatuses = [
                RecordType::STATUS_CANDIDATE,
                RecordType::STATUS_CONFIRMED,
            ];
            if ($canonicalId !== null) {
                $allowedStatuses[] = RecordType::STATUS_VOID;
            }
            if (! in_array($record->status, $allowedStatuses, true)) {
                $report['invalid_statuses']++;
                $report['errors'][] = [
                    'record_id' => $item['record_id'],
                    'error' => 'invalid_status',
                    'status' => $record->status,
                ];
                continue;
            }

            $conflict = RecordType::query()
                ->whereKeyNot($record->id)
                ->where('record_type', 'perfect')
                ->where('gender', $item['gender'])
                ->where(
                    'certification_number_value',
                    $item['certification_number_value']
                )
                ->first();
            $canonical = null;
            if ($canonicalId !== null) {
                $canonical = RecordType::query()
                    ->with('proBowler:id,license_no,name_kanji')
                    ->find($canonicalId);
                if (
                    ! $conflict
                    || ! $canonical
                    || (int) $conflict->id !== (int) $canonical->id
                    || (int) $canonical->pro_bowler_id
                        !== (int) $record->pro_bowler_id
                    || $canonical->record_type !== 'perfect'
                    || $canonical->status !== RecordType::STATUS_CONFIRMED
                    || $canonical->gender !== $item['gender']
                    || (int) $canonical->certification_number_value
                        !== (int) $item['certification_number_value']
                ) {
                    $report['certification_conflicts']++;
                    $report['errors'][] = [
                        'record_id' => $item['record_id'],
                        'error' => 'canonical_record_mismatch',
                        'canonical_record_id' => $canonicalId,
                        'conflicting_record_id' => $conflict?->id,
                    ];
                    continue;
                }
            } elseif ($conflict) {
                $report['certification_conflicts']++;
                $report['errors'][] = [
                    'record_id' => $item['record_id'],
                    'error' => 'certification_conflict',
                    'conflicting_record_id' => $conflict->id,
                    'certification_number_value' =>
                        $item['certification_number_value'],
                ];
                continue;
            }

            $report['records_matched']++;
            $licenseNo = $item['license_no'];
            $verifiedMinimums[$licenseNo] = max(
                (int) ($verifiedMinimums[$licenseNo] ?? 0),
                (int) $item['official_player_perfect_count']
            );
            if ($canonical) {
                $report['duplicate_candidates']++;
                if ($record->status === RecordType::STATUS_VOID) {
                    $report['already_void']++;
                }
            } elseif ($record->status === RecordType::STATUS_CONFIRMED) {
                $report['already_confirmed']++;
            } else {
                $report['candidate_records']++;
            }

            if (! $force) {
                continue;
            }

            $officialAttributes = [
                'tournament_name' => $item['tournament_name'],
                'game_numbers' => $item['game_numbers'],
                'awarded_on' => $item['awarded_on'],
                'certification_number' => 'JPBA公認 第'
                    . $item['certification_number_value']
                    . '号',
                'certification_number_value' =>
                    $item['certification_number_value'],
                'stage' => $item['stage'],
                'shift' => $item['shift'],
                'gender' => $item['gender'],
                'source_type' => 'official_tournament_correction',
                'source_url' => $item['source_url'],
                'source_label' => 'JPBA公式大会ページ・最終成績',
                'evidence_text' => $item['evidence_text'],
                'notes' => '公式大会ページまたは最終成績PDFで、公認番号・達成日・達成ゲームを確認',
            ];

            if ($canonical) {
                $canonical->fill([
                    ...$officialAttributes,
                    'tournament_id' => $canonical->tournament_id
                        ?: $record->tournament_id,
                    'source_game_score_id' => $canonical->source_game_score_id
                        ?: $record->source_game_score_id,
                ]);
                $canonical->save();
                $report['canonical_records_updated']++;

                if ($record->status !== RecordType::STATUS_VOID) {
                    $record->fill([
                        'status' => RecordType::STATUS_VOID,
                        'warning' => '確定レコードID '
                            . $canonical->id
                            . ' と同一のため統合',
                        'notes' => '得点自動検出候補。公式資料で確定済みのレコードID '
                            . $canonical->id
                            . ' と同一のため無効化（履歴保持）',
                    ])->save();
                    $report['candidates_voided']++;
                }
                continue;
            }

            $record->fill($officialAttributes);
            $record->save();
            $report['records_updated']++;

            if ($confirm && $record->status !== RecordType::STATUS_CONFIRMED) {
                $records->confirm($record);
                $report['records_confirmed']++;
            }
        }

        if ($force && $confirm) {
            foreach ($verifiedMinimums as $licenseNo => $minimum) {
                $bowler = ProBowler::query()
                    ->where('license_no', $licenseNo)
                    ->first();
                if (! $bowler || (int) $bowler->perfect_count >= $minimum) {
                    continue;
                }

                $increase = $minimum - (int) $bowler->perfect_count;
                $bowler->perfect_count = $minimum;
                $bowler->award_total_count = (int) $bowler->perfect_count
                    + (int) $bowler->eight_hundred_count
                    + (int) $bowler->seven_ten_count;
                $bowler->save();
                $report['authoritative_totals_reconciled']++;
                $report['perfect_count_increase'] += $increase;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ));
        } else {
            $this->info('JPBA公式資料による未確認パーフェクト候補の照合: '
                . $report['mode']);
            foreach ($report as $key => $value) {
                if (! is_array($value)) {
                    $this->line($key . ': ' . $value);
                }
            }
        }

        return (
            $report['records_missing'] > 0
            || $report['identity_mismatches'] > 0
            || $report['certification_conflicts'] > 0
            || $report['invalid_statuses'] > 0
        )
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function dataset(): array
    {
        $path = database_path(
            'data/jpba_official_2026_perfect_candidate_resolutions.json'
        );
        if (! is_file($path)) {
            throw new RuntimeException(
                'Perfect candidate resolution dataset is missing: ' . $path
            );
        }

        try {
            $rows = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException(
                'Perfect candidate resolution dataset is invalid: '
                    . $e->getMessage(),
                previous: $e
            );
        }

        if (! is_array($rows)) {
            throw new RuntimeException(
                'Perfect candidate resolution dataset must be an array.'
            );
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<int,string>
     */
    private function identityErrors(RecordType $record, array $item): array
    {
        $errors = [];
        $bowler = $record->proBowler;

        if ($record->record_type !== 'perfect') {
            $errors[] = 'record_type';
        }
        if (! $bowler || $bowler->license_no !== $item['license_no']) {
            $errors[] = 'license_no';
        }
        if (
            ! $bowler
            || $this->normalize((string) $bowler->name_kanji)
                !== $this->normalize((string) $item['player_name'])
        ) {
            $errors[] = 'player_name';
        }
        if (
            $record->source_type !== 'score_auto'
            && $record->source_type !== 'official_tournament_correction'
        ) {
            $errors[] = 'source_type';
        }

        return $errors;
    }

    private function normalize(string $value): string
    {
        $value = mb_convert_kana($value, 'asKV', 'UTF-8');

        return preg_replace('/[\s\x{3000}・･]+/u', '', $value) ?: $value;
    }
}
