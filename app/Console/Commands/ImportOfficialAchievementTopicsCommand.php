<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Models\RecordType;
use App\Services\AchievementRecordService;
use App\Services\JpbaOfficialAchievementTopicService;
use Illuminate\Console\Command;
use Throwable;

class ImportOfficialAchievementTopicsCommand extends Command
{
    protected $signature = 'jpba:import-official-achievement-topics
        {--url=* : Import only the specified official topic URL(s)}
        {--year-from=2018 : First archive year}
        {--year-to= : Last archive year; defaults to the current year}
        {--sleep-ms=150 : Sleep between official-site requests}
        {--force : Write records. Without this option, run as a dry-run}
        {--confirm : Confirm conservatively parsed official records}
        {--json : Output a machine-readable report}';

    protected $description = 'Import verifiable JPBA certified-achievement details from official topic archives.';

    public function handle(
        JpbaOfficialAchievementTopicService $topics,
        AchievementRecordService $records
    ): int {
        $force = (bool) $this->option('force');
        $confirm = (bool) $this->option('confirm');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $urls = $this->urls();

        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'pages_checked' => 0,
            'pages_missing' => 0,
            'pages_with_records' => 0,
            'occurrences_parsed' => 0,
            'players_matched' => 0,
            'unmatched_players' => 0,
            'implausible_certifications' => 0,
            'certification_conflicts' => 0,
            'duplicate_certification_mentions' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_confirmed' => 0,
            'errors' => 0,
            'by_type' => [
                'perfect' => 0,
                'eight_hundred' => 0,
                'seven_ten' => 0,
            ],
            'samples' => [],
            'unmatched_samples' => [],
            'implausible_samples' => [],
            'conflict_samples' => [],
            'duplicate_mention_samples' => [],
            'error_samples' => [],
        ];

        foreach ($urls as $url) {
            $report['pages_checked']++;
            try {
                $occurrences = $topics->fetch($url);
                if ($occurrences !== []) {
                    $report['pages_with_records']++;
                }
                $report['occurrences_parsed'] += count($occurrences);

                foreach ($occurrences as $occurrence) {
                    $bowler = $this->resolveBowler($occurrence);
                    if (! $bowler) {
                        $report['unmatched_players']++;
                        if (count($report['unmatched_samples']) < 20) {
                            $report['unmatched_samples'][] = [
                                'url' => $url,
                                'license_number' => $occurrence['license_number'],
                                'evidence' => $occurrence['evidence_text'],
                            ];
                        }
                        continue;
                    }

                    if (! $this->plausibleCertificationNumber($occurrence, $bowler)) {
                        $report['implausible_certifications']++;
                        if (count($report['implausible_samples']) < 20) {
                            $report['implausible_samples'][] = [
                                'url' => $url,
                                'license_no' => $bowler->license_no,
                                'name' => $bowler->name_kanji,
                                'record_type' => $occurrence['record_type'],
                                'certification_number' => $occurrence['certification_number'],
                                'evidence' => $occurrence['evidence_text'],
                            ];
                        }
                        continue;
                    }

                    $report['players_matched']++;
                    $report['by_type'][$occurrence['record_type']]++;
                    if (count($report['samples']) < 20) {
                        $report['samples'][] = [
                            'license_no' => $bowler->license_no,
                            'name' => $bowler->name_kanji,
                            'record_type' => $occurrence['record_type'],
                            'certification_number' => $occurrence['certification_number'],
                            'awarded_on' => $occurrence['awarded_on'],
                            'tournament_name' => $occurrence['tournament_name'],
                            'game_numbers' => $occurrence['game_numbers'],
                            'frame_number' => $occurrence['frame_number'],
                            'series_label' => $occurrence['series_label'],
                            'source_url' => $url,
                        ];
                    }
                    if (! $force) {
                        continue;
                    }

                    $gender = strtoupper(substr((string) $bowler->license_no, 0, 1));
                    $detectionKey = implode(':', [
                        'official_topic',
                        $occurrence['record_type'],
                        $gender,
                        $occurrence['certification_number_value'],
                    ]);
                    $record = $this->findExistingRecord(
                        $occurrence,
                        $bowler,
                        $gender,
                        $detectionKey
                    );
                    if (
                        $record
                        && (int) $record->certification_number_value
                            === (int) $occurrence['certification_number_value']
                        && (int) $record->pro_bowler_id !== (int) $bowler->id
                    ) {
                        $report['certification_conflicts']++;
                        if (count($report['conflict_samples']) < 20) {
                            $report['conflict_samples'][] = [
                                'url' => $url,
                                'record_id' => $record->id,
                                'existing_pro_bowler_id' => $record->pro_bowler_id,
                                'parsed_pro_bowler_id' => $bowler->id,
                                'record_type' => $occurrence['record_type'],
                                'certification_number' => $occurrence['certification_number'],
                                'evidence' => $occurrence['evidence_text'],
                            ];
                        }
                        continue;
                    }
                    if (
                        $record
                        && $record->source_type === 'official_tournament_correction'
                    ) {
                        $report['duplicate_certification_mentions']++;
                        if (count($report['duplicate_mention_samples']) < 20) {
                            $report['duplicate_mention_samples'][] = [
                                'url' => $url,
                                'original_url' => $record->source_url,
                                'record_id' => $record->id,
                                'license_no' => $bowler->license_no,
                                'record_type' => $occurrence['record_type'],
                                'certification_number' => $occurrence['certification_number'],
                            ];
                        }
                        continue;
                    }
                    if (
                        $record
                        && (int) $record->certification_number_value
                            === (int) $occurrence['certification_number_value']
                        && (int) $record->pro_bowler_id === (int) $bowler->id
                        && $record->source_type === 'official_topic'
                        && $record->source_url
                        && $record->source_url !== $url
                    ) {
                        $report['duplicate_certification_mentions']++;
                        if (count($report['duplicate_mention_samples']) < 20) {
                            $report['duplicate_mention_samples'][] = [
                                'url' => $url,
                                'original_url' => $record->source_url,
                                'record_id' => $record->id,
                                'license_no' => $bowler->license_no,
                                'record_type' => $occurrence['record_type'],
                                'certification_number' => $occurrence['certification_number'],
                            ];
                        }
                        continue;
                    }

                    $attributes = [
                        ...$occurrence,
                        'pro_bowler_id' => $bowler->id,
                        'gender' => in_array($gender, ['M', 'F'], true) ? $gender : null,
                        'registration_mode' => RecordType::MODE_HISTORICAL,
                        'source_type' => 'official_topic',
                        'detected_at' => now(),
                        'notes' => '現在確認できる大会分のみデータとして記載',
                    ];
                    unset($attributes['license_number']);

                    if ($record) {
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
            } catch (Throwable $e) {
                if (
                    str_contains($e->getMessage(), 'HTTP status 404')
                    || str_contains($e->getMessage(), 'status code 404')
                ) {
                    $report['pages_missing']++;
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                    continue;
                }

                $report['errors']++;
                if (count($report['error_samples']) < 20) {
                    $report['error_samples'][] = [
                        'url' => $url,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('JPBA公式トピックス公認記録取込: ' . $report['mode']);
            foreach ($report as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $this->line($key . ': ' . $value);
            }
            foreach ($report['by_type'] as $type => $count) {
                $this->line($type . ': ' . $count);
            }
        }

        return $report['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Prefer an already imported certification. If the official article is
     * enriching an auto-detected score candidate, reuse that row so a single
     * achievement can never become two confirmed details.
     *
     * @param array<string,mixed> $occurrence
     */
    private function findExistingRecord(
        array $occurrence,
        ProBowler $bowler,
        string $gender,
        string $detectionKey
    ): ?RecordType {
        $record = RecordType::query()
            ->where('record_type', $occurrence['record_type'])
            ->where('gender', $gender)
            ->where(
                'certification_number_value',
                $occurrence['certification_number_value']
            )
            ->first();
        if ($record) {
            return $record;
        }

        $record = RecordType::query()->where('detection_key', $detectionKey)->first();
        if ($record) {
            return $record;
        }

        $scoreCandidates = RecordType::query()
            ->where('pro_bowler_id', $bowler->id)
            ->where('record_type', $occurrence['record_type'])
            ->where('status', RecordType::STATUS_CANDIDATE)
            ->where('source_type', 'score_auto')
            ->whereDate('awarded_on', $occurrence['awarded_on'])
            ->get();

        if ($scoreCandidates->count() === 1) {
            return $scoreCandidates->first();
        }

        $gameNumber = null;
        if (preg_match('/(\d+)\s*G/u', (string) ($occurrence['game_numbers'] ?? ''), $match)) {
            $gameNumber = (int) $match[1];
        }
        if ($gameNumber !== null) {
            $gameMatches = $scoreCandidates->filter(
                fn (RecordType $candidate): bool => preg_match(
                    '/(?:^|\D)' . preg_quote((string) $gameNumber, '/') . '\s*G/u',
                    (string) $candidate->game_numbers
                ) === 1
            );
            if ($gameMatches->count() === 1) {
                return $gameMatches->first();
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $occurrence
     */
    private function resolveBowler(array $occurrence): ?ProBowler
    {
        $number = (int) $occurrence['license_number'];
        $candidates = ProBowler::query()
            ->where('license_no_num', $number)
            ->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $evidence = $this->normalize((string) $occurrence['evidence_text']);
        $nameMatches = $candidates->filter(function (ProBowler $bowler) use ($evidence): bool {
            $name = $this->normalize((string) $bowler->name_kanji);

            return $name !== '' && str_contains($evidence, $name);
        });
        if ($nameMatches->count() === 1) {
            return $nameMatches->first();
        }

        $gender = $this->genderFromCertification($occurrence);
        if ($gender !== null) {
            $genderMatches = $candidates->filter(
                fn (ProBowler $bowler): bool => str_starts_with(
                    strtoupper((string) $bowler->license_no),
                    $gender
                )
            );
            if ($genderMatches->count() === 1) {
                return $genderMatches->first();
            }
        }

        return null;
    }

    /**
     * Topic pages sometimes describe an achievement as the tournament's
     * "third perfect", close to an unrelated certification phrase. Archive
     * years covered by this importer have much higher official sequence
     * numbers for male achievements (and for perfect games by either gender).
     * Reject those impossible combinations before they can reserve a number.
     *
     * @param array<string,mixed> $occurrence
     */
    private function plausibleCertificationNumber(
        array $occurrence,
        ProBowler $bowler
    ): bool {
        $number = (int) ($occurrence['certification_number_value'] ?? 0);
        $gender = strtoupper(substr((string) $bowler->license_no, 0, 1));

        if ($number < 1 || ! in_array($gender, ['M', 'F'], true)) {
            return false;
        }

        return match ($occurrence['record_type'] ?? null) {
            'perfect' => $gender === 'M'
                ? $number >= 1000
                : $number >= 100 && $number < 1000,
            'eight_hundred' => $gender === 'M'
                ? $number >= 200 && $number < 1000
                : $number < 200,
            'seven_ten' => $gender === 'M'
                ? $number >= 100 && $number < 1000
                : $number < 100,
            default => false,
        };
    }

    /**
     * Archive-era JPBA sequences are separated by gender and do not overlap.
     * This is used only to disambiguate the same numeric M/F license number.
     *
     * @param array<string,mixed> $occurrence
     */
    private function genderFromCertification(array $occurrence): ?string
    {
        $number = (int) ($occurrence['certification_number_value'] ?? 0);

        return match ($occurrence['record_type'] ?? null) {
            'perfect' => $number >= 1000 ? 'M' : ($number >= 100 ? 'F' : null),
            'eight_hundred' => $number >= 200 ? 'M' : ($number > 0 ? 'F' : null),
            'seven_ten' => $number >= 100 ? 'M' : ($number > 0 ? 'F' : null),
            default => null,
        };
    }

    /**
     * @return array<int,string>
     */
    private function urls(): array
    {
        $specified = array_values(array_filter(array_map(
            fn ($url) => trim((string) $url),
            (array) $this->option('url')
        )));
        if ($specified !== []) {
            return array_values(array_unique($specified));
        }

        $from = max(2018, (int) $this->option('year-from'));
        $to = (int) ($this->option('year-to') ?: now()->year);
        $to = max($from, min((int) now()->year, $to));
        $urls = [];

        for ($year = $from; $year <= $to; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                if ($year === (int) now()->year && $month > (int) now()->month) {
                    break;
                }
                $monthText = sprintf('%02d', $month);
                $urls[] = $year >= 2020
                    ? JpbaOfficialAchievementTopicService::BASE_URL
                        . '/topics/' . $year . '/' . $monthText . '.html'
                    : JpbaOfficialAchievementTopicService::BASE_URL
                        . '/topics/' . $year . '/topics' . $monthText . '.html';
            }
        }

        return $urls;
    }

    private function normalize(string $value): string
    {
        $value = mb_convert_kana($value, 'asKV', 'UTF-8');

        return preg_replace('/[\s\x{3000}・･·]+/u', '', $value) ?: $value;
    }
}
