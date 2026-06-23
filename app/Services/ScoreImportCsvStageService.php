<?php

namespace App\Services;

use App\Models\ScoreImportBatch;
use App\Models\ScoreImportRow;
use App\Models\Tournament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ScoreImportCsvStageService
{
    private const PARSER_VERSION = 'score_csv_stage_v1';

    public function import(Tournament $tournament, UploadedFile $file, ?Authenticatable $user = null, array $options = []): ScoreImportBatch
    {
        $storedPath = $file->storeAs(
            'score-imports/' . $tournament->id,
            now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $this->safeFilename($file->getClientOriginalName())
        );

        $batch = ScoreImportBatch::create([
            'tournament_id' => $tournament->id,
            'import_type' => 'csv',
            'source_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'draft',
            'parser_version' => self::PARSER_VERSION,
            'imported_by' => $user ? (int) $user->getAuthIdentifier() : null,
            'notes' => $this->buildNotes($options),
        ]);

        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            $batch->update([
                'status' => 'failed',
                'error_message' => 'CSVを開けません。',
            ]);

            throw new InvalidArgumentException('CSVを開けません。');
        }

        try {
            @stream_filter_append($handle, 'convert.iconv.CP932/UTF-8//IGNORE');

            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($header) || $this->isBlankRow($header)) {
                throw new InvalidArgumentException('CSVのヘッダー行を読めません。');
            }

            $columns = $this->resolveColumns($header);
            $lookups = $this->buildLookups((int) $tournament->id);

            DB::transaction(function () use ($batch, $handle, $header, $columns, $lookups, $options): void {
                $rowCount = 0;
                $parsedCount = 0;
                $needsReviewCount = 0;
                $lineNumber = 1;

                while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    $lineNumber++;

                    if ($this->isBlankRow($values)) {
                        continue;
                    }

                    $rowCount++;

                    $mapped = $this->mapRow($values, $columns, $options);
                    $match = $this->matchPlayer($mapped, $lookups);
                    $issues = $this->detectIssues($mapped, $match);
                    $parseStatus = empty($issues) ? 'parsed' : 'needs_review';

                    if ($parseStatus === 'parsed') {
                        $parsedCount++;
                    } else {
                        $needsReviewCount++;
                    }

                    $row = ScoreImportRow::create([
                        'score_import_batch_id' => $batch->id,
                        'row_number' => $lineNumber,
                        'raw_payload' => [
                            'header' => array_values($header),
                            'values' => array_values($values),
                            'mapped' => $mapped,
                        ],
                        'parse_status' => $parseStatus,
                        'confidence' => $match['confidence'],
                        'tournament_participant_id' => $match['tournament_participant_id'],
                        'pro_bowler_id' => $match['pro_bowler_id'],
                        'license_number' => $mapped['license_number'],
                        'name' => $mapped['name'],
                        'entry_number' => $mapped['entry_number'],
                        'stage' => $mapped['stage'],
                        'shift' => $mapped['shift'],
                        'gender' => $mapped['gender'],
                        'game_number' => $mapped['game_number'],
                        'score' => $mapped['score'],
                        'error_message' => empty($issues) ? null : implode(', ', $issues),
                    ]);

                    foreach ($match['candidates'] as $candidate) {
                        $row->candidates()->create($candidate);
                    }
                }

                $batch->update([
                    'status' => $rowCount === 0 ? 'failed' : ($needsReviewCount > 0 ? 'reviewing' : 'parsed'),
                    'row_count' => $rowCount,
                    'accepted_row_count' => $parsedCount,
                    'rejected_row_count' => $needsReviewCount,
                    'parsed_at' => now(),
                    'error_message' => $rowCount === 0 ? 'CSVに取込対象行がありません。' : null,
                ]);
            });
        } catch (Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'parsed_at' => now(),
            ]);

            throw $e;
        } finally {
            fclose($handle);
        }

        return $batch->fresh();
    }

    private function resolveColumns(array $header): array
    {
        $headerMap = [];
        foreach ($header as $index => $name) {
            $key = $this->normalizeHeader($name);
            if ($key !== '' && ! array_key_exists($key, $headerMap)) {
                $headerMap[$key] = $index;
            }
        }

        $col = function (array $aliases) use ($headerMap): ?int {
            foreach ($aliases as $alias) {
                $key = $this->normalizeHeader($alias);
                if (array_key_exists($key, $headerMap)) {
                    return $headerMap[$key];
                }
            }

            return null;
        };

        return [
            'license_number' => $col(['license_number', 'license_no', 'license', 'ライセンス番号', 'ライセンスNo', '会員番号']),
            'name' => $col(['name', 'player_name', '氏名', '名前', '選手名', '氏名漢字']),
            'entry_number' => $col(['entry_number', 'entry_no', 'entry', 'エントリー番号', '受付番号', '番号', 'no']),
            'stage' => $col(['stage', 'round', 'ステージ', 'ラウンド', '種目']),
            'shift' => $col(['shift', 'シフト', '班']),
            'gender' => $col(['gender', 'sex', '性別']),
            'game_number' => $col(['game_number', 'game_no', 'game', 'g', 'ゲーム番号', 'ゲーム', 'G']),
            'score' => $col(['score', 'スコア', '点数', '得点']),
        ];
    }

    private function mapRow(array $values, array $columns, array $options): array
    {
        $rawScore = $this->value($values, $columns['score']);
        $rawGameNumber = $this->value($values, $columns['game_number']);

        return [
            'license_number' => $this->compactValue($this->value($values, $columns['license_number'])),
            'name' => $this->cleanValue($this->value($values, $columns['name'])),
            'entry_number' => $this->compactValue($this->value($values, $columns['entry_number'])),
            'stage' => $this->cleanValue($this->value($values, $columns['stage']) ?: ($options['default_stage'] ?? '')),
            'shift' => $this->cleanValue($this->value($values, $columns['shift']) ?: ($options['default_shift'] ?? '')),
            'gender' => $this->cleanValue($this->value($values, $columns['gender']) ?: ($options['default_gender'] ?? '')),
            'game_number' => $this->intOrNull($rawGameNumber !== '' ? $rawGameNumber : ($options['default_game_number'] ?? null)),
            'score' => $this->intOrNull($rawScore),
        ];
    }

    private function detectIssues(array $mapped, array $match): array
    {
        $issues = [];

        if ($mapped['stage'] === '') {
            $issues[] = 'stage_missing';
        }

        if ($mapped['game_number'] === null) {
            $issues[] = 'game_number_missing';
        }

        if ($mapped['score'] === null) {
            $issues[] = 'score_missing';
        } elseif ($mapped['score'] < 0 || $mapped['score'] > 300) {
            $issues[] = 'score_out_of_range';
        }

        if ($mapped['license_number'] === '' && $mapped['name'] === '' && $mapped['entry_number'] === '') {
            $issues[] = 'player_identity_missing';
        } elseif ($match['tournament_participant_id'] === null && $match['pro_bowler_id'] === null) {
            $issues[] = 'player_unmatched';
        }

        if ($match['ambiguous']) {
            $issues[] = 'player_ambiguous';
        }

        return $issues;
    }

    private function matchPlayer(array $mapped, array $lookups): array
    {
        $participantMatches = [];
        $proMatches = [];

        $licenseKey = $this->identityKey($mapped['license_number']);
        $nameKey = $this->identityKey($mapped['name']);

        if ($licenseKey !== '') {
            $participantMatches = $lookups['participants_by_license'][$licenseKey] ?? [];
            $proMatches = $lookups['pros_by_license'][$licenseKey] ?? [];
        }

        if (empty($participantMatches) && $nameKey !== '') {
            $participantMatches = $lookups['participants_by_name'][$nameKey] ?? [];
        }

        if (empty($proMatches) && $nameKey !== '') {
            $proMatches = $lookups['pros_by_name'][$nameKey] ?? [];
        }

        $candidates = [];
        foreach ($participantMatches as $index => $participant) {
            $candidates[] = [
                'candidate_type' => 'participant',
                'candidate_value' => $participant->display_name ?: $participant->pro_bowler_license_no,
                'tournament_participant_id' => $participant->id,
                'pro_bowler_id' => $participant->pro_bowler_id,
                'confidence' => $licenseKey !== '' ? 95 : 80,
                'rank' => $index + 1,
                'payload' => [
                    'pro_bowler_license_no' => $participant->pro_bowler_license_no,
                    'display_license_no' => $participant->display_license_no,
                    'display_name' => $participant->display_name,
                ],
                'is_selected' => count($participantMatches) === 1,
            ];
        }

        foreach ($proMatches as $index => $pro) {
            $candidates[] = [
                'candidate_type' => 'pro_bowler',
                'candidate_value' => $pro->name_kanji ?: $pro->license_no,
                'tournament_participant_id' => null,
                'pro_bowler_id' => $pro->id,
                'confidence' => $licenseKey !== '' ? 90 : 70,
                'rank' => $index + 1,
                'payload' => [
                    'license_no' => $pro->license_no,
                    'name_kanji' => $pro->name_kanji,
                ],
                'is_selected' => empty($participantMatches) && count($proMatches) === 1,
            ];
        }

        $selectedParticipant = count($participantMatches) === 1 ? $participantMatches[0] : null;
        $selectedPro = count($proMatches) === 1 ? $proMatches[0] : null;
        $ambiguous = count($participantMatches) > 1 || (empty($participantMatches) && count($proMatches) > 1);

        return [
            'tournament_participant_id' => $selectedParticipant?->id,
            'pro_bowler_id' => $selectedParticipant?->pro_bowler_id ?: $selectedPro?->id,
            'confidence' => $selectedParticipant ? 95 : ($selectedPro ? 90 : null),
            'ambiguous' => $ambiguous,
            'candidates' => $candidates,
        ];
    }

    private function buildLookups(int $tournamentId): array
    {
        $participants = DB::table('tournament_participants')
            ->where('tournament_id', $tournamentId)
            ->select('id', 'pro_bowler_id', 'pro_bowler_license_no', 'display_license_no', 'display_name')
            ->get();

        $pros = DB::table('pro_bowlers')
            ->select('id', 'license_no', 'name_kanji')
            ->get();

        $lookups = [
            'participants_by_license' => [],
            'participants_by_name' => [],
            'pros_by_license' => [],
            'pros_by_name' => [],
        ];

        foreach ($participants as $participant) {
            foreach ([$participant->pro_bowler_license_no, $participant->display_license_no] as $license) {
                $key = $this->identityKey($license);
                if ($key !== '') {
                    $lookups['participants_by_license'][$key][] = $participant;
                }
            }

            $nameKey = $this->identityKey($participant->display_name);
            if ($nameKey !== '') {
                $lookups['participants_by_name'][$nameKey][] = $participant;
            }
        }

        foreach ($pros as $pro) {
            $licenseKey = $this->identityKey($pro->license_no);
            if ($licenseKey !== '') {
                $lookups['pros_by_license'][$licenseKey][] = $pro;
            }

            $nameKey = $this->identityKey($pro->name_kanji);
            if ($nameKey !== '') {
                $lookups['pros_by_name'][$nameKey][] = $pro;
            }
        }

        return $lookups;
    }

    private function normalizeHeader(mixed $value): string
    {
        return mb_strtolower($this->identityKey($value), 'UTF-8');
    }

    private function identityKey(mixed $value): string
    {
        $value = $this->compactValue($value);

        return mb_strtolower($value, 'UTF-8');
    }

    private function compactValue(mixed $value): string
    {
        $value = $this->cleanValue($value);
        $value = preg_replace('/[\x{00A0}\x{3000}\s]+/u', '', $value);

        return $value ?? '';
    }

    private function cleanValue(mixed $value): string
    {
        $value = (string) ($value ?? '');
        $value = preg_replace('/^\x{FEFF}/u', '', $value);
        $value = str_replace(["\r", "\n", "\t"], '', $value);
        $value = trim($value);

        return mb_convert_kana($value, 'asKV', 'UTF-8');
    }

    private function value(array $values, ?int $index): string
    {
        if ($index === null || ! array_key_exists($index, $values)) {
            return '';
        }

        return (string) $values[$index];
    }

    private function intOrNull(mixed $value): ?int
    {
        $value = $this->compactValue($value);
        if ($value === '' || ! preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function safeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\pL\pN._-]+/u', '_', $name);
        $name = trim((string) $name, '._-');

        return $name !== '' ? $name : 'scores.csv';
    }

    private function buildNotes(array $options): ?string
    {
        $notes = [];

        foreach (['default_stage', 'default_shift', 'default_gender', 'default_game_number'] as $key) {
            if (($options[$key] ?? '') !== '') {
                $notes[] = $key . '=' . $options[$key];
            }
        }

        return empty($notes) ? null : implode(' / ', $notes);
    }
}
