<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Models\RecordType;
use App\Services\AchievementRecordService;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

class ImportOfficial2016AchievementRecordsCommand extends Command
{
    protected $signature = 'jpba:import-official-achievements-2016
        {--force : Write records. Without this option, run as a dry-run}
        {--confirm : Confirm records backed by the official tournament page}
        {--json : Output a machine-readable report}';

    protected $description = 'Import verified 2016 JPBA achievement details from official tournament pages.';

    public function handle(AchievementRecordService $records): int
    {
        $force = (bool) $this->option('force');
        $confirm = (bool) $this->option('confirm');
        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'dataset_rows' => 0,
            'players_matched' => 0,
            'players_missing' => 0,
            'name_mismatches' => 0,
            'certification_conflicts' => 0,
            'score_candidates_reused' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_confirmed' => 0,
            'by_type' => [
                'perfect' => 0,
                'eight_hundred' => 0,
                'seven_ten' => 0,
            ],
            'missing_samples' => [],
            'name_mismatch_samples' => [],
            'conflict_samples' => [],
        ];

        foreach ($this->dataset() as $item) {
            $report['dataset_rows']++;
            $report['by_type'][$item['record_type']]++;

            $bowler = ProBowler::query()
                ->where('license_no', $item['license_no'])
                ->first();
            if (! $bowler) {
                $report['players_missing']++;
                $report['missing_samples'][] = [
                    'license_no' => $item['license_no'],
                    'player_name' => $item['player_name'],
                    'certification_number' => $item['certification_number_value'],
                ];
                continue;
            }

            $report['players_matched']++;
            if (
                $this->normalize((string) $bowler->name_kanji)
                !== $this->normalize((string) $item['player_name'])
            ) {
                $report['name_mismatches']++;
                $report['name_mismatch_samples'][] = [
                    'license_no' => $item['license_no'],
                    'database_name' => $bowler->name_kanji,
                    'official_name' => $item['player_name'],
                ];
            }

            $record = RecordType::query()
                ->where('record_type', $item['record_type'])
                ->where('gender', $item['gender'])
                ->where(
                    'certification_number_value',
                    $item['certification_number_value']
                )
                ->first();

            if ($record && (int) $record->pro_bowler_id !== (int) $bowler->id) {
                $report['certification_conflicts']++;
                $report['conflict_samples'][] = [
                    'record_id' => $record->id,
                    'record_type' => $item['record_type'],
                    'gender' => $item['gender'],
                    'certification_number' => $item['certification_number_value'],
                    'existing_pro_bowler_id' => $record->pro_bowler_id,
                    'expected_pro_bowler_id' => $bowler->id,
                    'expected_license_no' => $item['license_no'],
                ];
                continue;
            }

            $detectionKey = implode(':', [
                'official_tournament_2016',
                $item['record_type'],
                $item['gender'],
                $item['certification_number_value'],
            ]);
            if (! $record) {
                $record = RecordType::query()
                    ->where('detection_key', $detectionKey)
                    ->first();
            }
            if (! $record) {
                $record = $this->matchingScoreCandidate($item, $bowler);
                if ($record) {
                    $report['score_candidates_reused']++;
                }
            }

            if (! $force) {
                continue;
            }

            $attributes = [
                'record_type' => $item['record_type'],
                'pro_bowler_id' => $bowler->id,
                'tournament_name' => $item['tournament_name'],
                'game_numbers' => $item['game_numbers'] ?? null,
                'frame_number' => $item['frame_number'] ?? null,
                'awarded_on' => $item['awarded_on'],
                'certification_number' => '公認'
                    . $item['certification_number_value']
                    . '号',
                'certification_number_value' => $item['certification_number_value'],
                'stage' => $item['stage'] ?? null,
                'shift' => $item['shift'] ?? null,
                'gender' => $item['gender'],
                'series_label' => $item['series_label'] ?? null,
                'series_start_game' => $item['series_start_game'] ?? null,
                'series_end_game' => $item['series_end_game'] ?? null,
                'registration_mode' => RecordType::MODE_HISTORICAL,
                'source_type' => 'official_tournament',
                'source_url' => $item['source_url'],
                'source_label' => 'JPBA公式大会ページ（2016年）',
                'evidence_text' => $item['evidence_text'],
                'detected_at' => now(),
                'notes' => $item['notes']
                    ?? '現在確認できる大会分のみデータとして記載',
            ];

            if ($record) {
                if (
                    $record->source_type === 'official_tournament_correction'
                    && $record->status === RecordType::STATUS_CONFIRMED
                ) {
                    continue;
                }

                if (! $record->detection_key) {
                    $attributes['detection_key'] = $detectionKey;
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
                $report['records_updated']++;
            } else {
                $record = RecordType::query()->create([
                    ...$attributes,
                    'detection_key' => $detectionKey,
                    'status' => RecordType::STATUS_CANDIDATE,
                ]);
                $report['records_created']++;
            }

            if ($confirm && $record->status !== RecordType::STATUS_CONFIRMED) {
                $records->confirm($record);
                $report['records_confirmed']++;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ));
        } else {
            $this->info('2016年JPBA公式大会・公認記録取込: ' . $report['mode']);
            foreach ($report as $key => $value) {
                if (! is_array($value)) {
                    $this->line($key . ': ' . $value);
                }
            }
            foreach ($report['by_type'] as $type => $count) {
                $this->line($type . ': ' . $count);
            }
        }

        return (
            $report['players_missing'] > 0
            || $report['certification_conflicts'] > 0
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
            'data/jpba_official_2016_achievement_records.json'
        );
        if (! is_file($path)) {
            throw new RuntimeException('2016 achievement dataset is missing: ' . $path);
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
                '2016 achievement dataset is invalid: ' . $e->getMessage(),
                previous: $e
            );
        }

        if (! is_array($rows)) {
            throw new RuntimeException('2016 achievement dataset must be an array.');
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function matchingScoreCandidate(
        array $item,
        ProBowler $bowler
    ): ?RecordType {
        $candidates = RecordType::query()
            ->where('pro_bowler_id', $bowler->id)
            ->where('record_type', $item['record_type'])
            ->where('status', RecordType::STATUS_CANDIDATE)
            ->where('source_type', 'score_auto')
            ->whereDate('awarded_on', $item['awarded_on'])
            ->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $expectedPosition = $this->normalize((string) ($item['game_numbers'] ?? ''));
        if ($expectedPosition === '') {
            return null;
        }

        $positionMatches = $candidates->filter(
            fn (RecordType $candidate): bool => $this->normalize(
                (string) $candidate->game_numbers
            ) === $expectedPosition
        );

        return $positionMatches->count() === 1
            ? $positionMatches->first()
            : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_convert_kana($value, 'asKV', 'UTF-8');

        return preg_replace('/[\s\x{3000}・･\x{FF65}]+/u', '', $value)
            ?: $value;
    }
}
