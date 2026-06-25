<?php

namespace App\Services;

use App\Models\ScoreImportBatch;
use App\Models\ScoreImportRow;
use App\Models\Tournament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScoreImportOcrResultStageService
{
    private const PARSER_VERSION = 'score_ocr_result_v1';

    public function importJson(
        Tournament $tournament,
        ScoreImportBatch $batch,
        UploadedFile $file,
        ?Authenticatable $user = null,
        array $options = []
    ): array {
        if ($batch->import_type !== 'score_sheet_image') {
            throw new InvalidArgumentException('OCR解析結果は写真/PDF原本バッチにだけ追加できます。');
        }

        $payload = $this->decodeJsonFile($file);
        $payloadRows = $this->extractRows($payload);
        if (empty($payloadRows)) {
            throw new InvalidArgumentException('OCR解析結果JSONに取込対象行がありません。');
        }

        $summary = DB::transaction(function () use ($tournament, $batch, $payloadRows, $file, $options): array {
            if ($batch->rows()->whereNotNull('confirmed_game_score_id')->exists()) {
                throw new InvalidArgumentException('反映済み行があるため、OCR解析結果を差し替えできません。');
            }

            $replaceExisting = (bool) ($options['replace_existing'] ?? false);
            $existingRowCount = $batch->rows()->count();
            if ($existingRowCount > 0 && ! $replaceExisting) {
                throw new InvalidArgumentException('既存の解析行があります。差し替える場合は「既存解析行を差し替える」を選択してください。');
            }

            if ($replaceExisting) {
                $batch->rows()->delete();
            }

            $lookups = $this->buildLookups((int) $tournament->id);
            $created = 0;
            $parsed = 0;
            $needsReview = 0;

            foreach ($payloadRows as $index => $payloadRow) {
                $mapped = $this->mapPayloadRow($payloadRow, $options);
                $match = $this->matchPlayer($mapped, $lookups);
                $issues = $this->detectIssues($mapped, $match);
                $parseStatus = empty($issues) ? 'parsed' : 'needs_review';

                if ($parseStatus === 'parsed') {
                    $parsed++;
                } else {
                    $needsReview++;
                }

                $row = ScoreImportRow::create([
                    'score_import_batch_id' => $batch->id,
                    'row_number' => $index + 1,
                    'raw_payload' => [
                        'parser_version' => self::PARSER_VERSION,
                        'source_filename' => $file->getClientOriginalName(),
                        'source_row_number' => $payloadRow['_source_row_number'] ?? ($index + 1),
                        'source_game_key' => $payloadRow['_source_game_key'] ?? null,
                        'ocr_payload' => $payloadRow,
                        'mapped' => $mapped,
                    ],
                    'parse_status' => $parseStatus,
                    'confidence' => $mapped['confidence'],
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

                $created++;
            }

            $batch->update([
                'status' => $needsReview > 0 ? 'reviewing' : 'parsed',
                'parser_version' => self::PARSER_VERSION,
                'row_count' => $created,
                'accepted_row_count' => $parsed,
                'rejected_row_count' => $needsReview,
                'parsed_at' => now(),
                'error_message' => null,
            ]);

            return [
                'created' => $created,
                'parsed' => $parsed,
                'needs_review' => $needsReview,
                'replaced_existing' => $replaceExisting ? $existingRowCount : 0,
            ];
        });

        app(ScoreImportOperationLogger::class)->log($batch->fresh(), 'ocr_json_stage', [
            'status' => 'success',
            'target_row_count' => $summary['created'],
            'created_count' => $summary['created'],
            'skipped_count' => $summary['needs_review'],
            'message' => 'OCR解析結果JSONを確認用行へ変換しました。',
            'payload' => [
                'source_filename' => $file->getClientOriginalName(),
                'parser_version' => self::PARSER_VERSION,
                'parsed_row_count' => $summary['parsed'],
                'needs_review_row_count' => $summary['needs_review'],
                'replaced_existing_row_count' => $summary['replaced_existing'],
            ],
        ], $user);

        return $summary;
    }

    private function decodeJsonFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        if ($content === false || trim($content) === '') {
            throw new InvalidArgumentException('OCR解析結果JSONを読み込めません。');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $payload = json_decode($content, true);
        if (! is_array($payload)) {
            throw new InvalidArgumentException('OCR解析結果JSONの形式が正しくありません。');
        }

        return $payload;
    }

    private function extractRows(array $payload): array
    {
        $sourceRows = array_is_list($payload) ? $payload : ($payload['rows'] ?? []);
        if (! is_array($sourceRows)) {
            return [];
        }

        $rows = [];
        foreach ($sourceRows as $index => $sourceRow) {
            if (! is_array($sourceRow)) {
                continue;
            }

            $baseRow = $sourceRow;
            unset($baseRow['games'], $baseRow['scores']);
            $baseRow['_source_row_number'] = $index + 1;

            if (isset($sourceRow['games']) && is_array($sourceRow['games'])) {
                foreach ($sourceRow['games'] as $gameIndex => $gameRow) {
                    if (! is_array($gameRow)) {
                        continue;
                    }

                    $rows[] = array_merge($baseRow, $gameRow, [
                        '_source_game_key' => $gameIndex,
                    ]);
                }

                continue;
            }

            if (isset($sourceRow['scores']) && is_array($sourceRow['scores'])) {
                foreach ($sourceRow['scores'] as $gameNumber => $score) {
                    $rows[] = array_merge($baseRow, [
                        'game_number' => $gameNumber,
                        'score' => $score,
                        '_source_game_key' => $gameNumber,
                    ]);
                }

                continue;
            }

            $rows[] = $baseRow;
        }

        return $rows;
    }

    private function mapPayloadRow(array $row, array $options): array
    {
        $rawGameNumber = $this->firstValue($row, ['game_number', 'game_no', 'game', 'g']);
        $rawScore = $this->firstValue($row, ['score', 'pin', 'pins', 'total']);

        return [
            'license_number' => $this->compactValue($this->firstValue($row, ['license_number', 'license_no', 'license'])),
            'name' => $this->cleanValue($this->firstValue($row, ['name', 'player_name'])),
            'entry_number' => $this->compactValue($this->firstValue($row, ['entry_number', 'entry_no', 'entry'])),
            'stage' => $this->cleanValue($this->firstValue($row, ['stage', 'round']) ?: ($options['default_stage'] ?? '')),
            'shift' => $this->cleanValue($this->firstValue($row, ['shift']) ?: ($options['default_shift'] ?? '')),
            'gender' => $this->cleanValue($this->firstValue($row, ['gender', 'sex']) ?: ($options['default_gender'] ?? '')),
            'game_number' => $this->intOrNull($rawGameNumber),
            'score' => $this->intOrNull($rawScore),
            'confidence' => $this->normalizeConfidence($this->firstValue($row, ['confidence', 'ocr_confidence'])),
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

    private function firstValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
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

    private function intOrNull(mixed $value): ?int
    {
        $value = $this->compactValue($value);
        if ($value === '' || ! preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function normalizeConfidence(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $confidence = (float) $value;
        if ($confidence > 0 && $confidence <= 1) {
            $confidence *= 100;
        }

        return max(0, min(100, round($confidence, 2)));
    }
}
