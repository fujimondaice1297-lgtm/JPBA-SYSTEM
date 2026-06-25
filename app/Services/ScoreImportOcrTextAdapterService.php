<?php

namespace App\Services;

use InvalidArgumentException;

class ScoreImportOcrTextAdapterService
{
    private const ADAPTER_VERSION = 'score_ocr_text_adapter_v1';

    public function adapt(string $text, array $options = []): array
    {
        $text = $this->normalizeText($text);
        if ($text === '') {
            throw new InvalidArgumentException('OCR/AI出力が空です。');
        }

        $warnings = [];
        $jsonPayload = $this->decodeJsonLikeText($text);
        if (is_array($jsonPayload)) {
            $rows = $this->canonicalRowsFromPayload($jsonPayload, $options, $warnings);
            $inputFormat = 'json';
        } else {
            $rows = $this->canonicalRowsFromTableText($text, $options, $warnings);
            $inputFormat = 'table_text';
        }

        if (empty($rows)) {
            throw new InvalidArgumentException('OCR/AI出力からスコア行を抽出できませんでした。');
        }

        return [
            'payload' => [
                'rows' => $rows,
            ],
            'summary' => [
                'adapter_version' => self::ADAPTER_VERSION,
                'input_format' => $inputFormat,
                'input_line_count' => count(preg_split('/\R/u', $text) ?: []),
                'row_count' => count($rows),
                'warning_count' => count($warnings),
                'warnings' => array_slice($warnings, 0, 20),
            ],
        ];
    }

    private function canonicalRowsFromPayload(array $payload, array $options, array &$warnings): array
    {
        $sourceRows = array_is_list($payload)
            ? $payload
            : $this->firstArrayValue($payload, ['rows', 'players', 'results', 'items', '解析結果', '行', '選手']);

        if (! is_array($sourceRows)) {
            return [];
        }

        $rows = [];
        foreach ($sourceRows as $index => $sourceRow) {
            if (! is_array($sourceRow)) {
                $warnings[] = sprintf('%d行目は配列ではないためスキップしました。', $index + 1);
                continue;
            }

            $row = $this->canonicalBaseRow($sourceRow, $options);
            $games = $this->canonicalGamesFromRow($sourceRow, $warnings, $index + 1);

            if (! empty($games)) {
                $row['games'] = $games;
            } else {
                $row['game_number'] = $this->intOrNull($this->firstValue($sourceRow, ['game_number', 'game_no', 'game', 'g', 'gameNumber', 'ゲーム番号', 'ゲーム', 'G']));
                $row['score'] = $this->intOrNull($this->firstValue($sourceRow, ['score', 'pin', 'pins', 'total', 'スコア', '点数', '得点', 'ピン']));
            }

            $rows[] = $this->dropEmptyValues($row);
        }

        return $rows;
    }

    private function canonicalRowsFromTableText(string $text, array $options, array &$warnings): array
    {
        $lines = array_values(array_filter(
            preg_split('/\R/u', $text) ?: [],
            fn (string $line): bool => $this->cleanValue($line) !== ''
        ));

        if (empty($lines)) {
            return [];
        }

        $table = $this->parseMarkdownTable($lines);
        if (empty($table)) {
            $table = $this->parseDelimitedLines($lines);
        }

        if (empty($table)) {
            return [];
        }

        $firstRow = $table[0] ?? [];
        if ($this->looksLikeHeader($firstRow)) {
            return $this->rowsFromHeaderTable($firstRow, array_slice($table, 1), $options, $warnings);
        }

        return $this->rowsFromHeaderlessTable($table, $options, $warnings);
    }

    private function rowsFromHeaderTable(array $header, array $dataRows, array $options, array &$warnings): array
    {
        $columns = $this->resolveColumns($header);
        $rows = [];

        foreach ($dataRows as $rowIndex => $values) {
            if ($this->isBlankRow($values) || $this->isMarkdownSeparatorRow($values)) {
                continue;
            }

            $base = [
                'license_number' => $this->compactValue($this->value($values, $columns['license_number'])),
                'name' => $this->cleanValue($this->value($values, $columns['name'])),
                'entry_number' => $this->compactValue($this->value($values, $columns['entry_number'])),
                'stage' => $this->cleanValue($this->value($values, $columns['stage']) ?: ($options['default_stage'] ?? '')),
                'shift' => $this->cleanValue($this->value($values, $columns['shift']) ?: ($options['default_shift'] ?? '')),
                'gender' => $this->cleanValue($this->value($values, $columns['gender']) ?: ($options['default_gender'] ?? '')),
                'confidence' => $this->normalizeConfidence($this->value($values, $columns['confidence'])),
            ];

            if (! empty($columns['score_columns'])) {
                $scores = [];
                foreach ($columns['score_columns'] as $scoreColumn) {
                    $score = $this->intOrNull($this->value($values, $scoreColumn['index']));
                    if ($score !== null) {
                        $scores[(string) $scoreColumn['game_number']] = $score;
                    }
                }

                if (! empty($scores)) {
                    $base['scores'] = $scores;
                    $rows[] = $this->dropEmptyValues($base);
                    continue;
                }
            }

            $base['game_number'] = $this->intOrNull($this->value($values, $columns['game_number']) ?: ($options['default_game_number'] ?? null));
            $base['score'] = $this->intOrNull($this->value($values, $columns['score']));
            if ($base['score'] === null) {
                $warnings[] = sprintf('表データ%d行目はスコアを抽出できませんでした。', $rowIndex + 2);
            }

            $rows[] = $this->dropEmptyValues($base);
        }

        return $rows;
    }

    private function rowsFromHeaderlessTable(array $table, array $options, array &$warnings): array
    {
        $rows = [];

        foreach ($table as $rowIndex => $values) {
            if ($this->isBlankRow($values)) {
                continue;
            }

            $line = implode(' ', array_map(fn (mixed $value): string => $this->cleanValue($value), $values));
            $pairedScores = $this->extractGameScorePairs($line);
            $withoutPairs = $pairedScores['text_without_pairs'];
            $scores = $pairedScores['scores'];

            if (empty($scores)) {
                $numericScores = [];
                foreach ($values as $value) {
                    $score = $this->intOrNull($value);
                    if ($score !== null && $score >= 0 && $score <= 300) {
                        $numericScores[] = $score;
                    }
                }

                foreach ($numericScores as $index => $score) {
                    $scores[(string) ($index + 1)] = $score;
                }
            }

            if (empty($scores)) {
                $warnings[] = sprintf('ヘッダーなし%d行目はスコアを抽出できませんでした。', $rowIndex + 1);
                continue;
            }

            $identityText = $this->removeScoresFromLine($withoutPairs, $scores);
            $identity = $this->extractIdentityFromText($identityText);

            $row = [
                'license_number' => $identity['license_number'],
                'name' => $identity['name'],
                'entry_number' => '',
                'stage' => $this->cleanValue($options['default_stage'] ?? ''),
                'shift' => $this->cleanValue($options['default_shift'] ?? ''),
                'gender' => $this->cleanValue($options['default_gender'] ?? ''),
                'scores' => $scores,
            ];

            if ($this->cleanValue($options['default_game_number'] ?? '') !== '' && count($scores) === 1) {
                $score = reset($scores);
                $row['game_number'] = $this->intOrNull($options['default_game_number']);
                $row['score'] = $score;
                unset($row['scores']);
            }

            $rows[] = $this->dropEmptyValues($row);
        }

        return $rows;
    }

    private function canonicalBaseRow(array $sourceRow, array $options): array
    {
        return [
            'license_number' => $this->compactValue($this->firstValue($sourceRow, ['license_number', 'license_no', 'license', 'licenseNumber', 'ライセンス番号', 'ライセンスNo', '会員番号'])),
            'name' => $this->cleanValue($this->firstValue($sourceRow, ['name', 'player_name', 'playerName', '氏名', '名前', '選手名'])),
            'entry_number' => $this->compactValue($this->firstValue($sourceRow, ['entry_number', 'entry_no', 'entry', 'entryNumber', 'エントリー番号', '受付番号', '番号'])),
            'stage' => $this->cleanValue($this->firstValue($sourceRow, ['stage', 'round', 'ステージ', 'ラウンド']) ?: ($options['default_stage'] ?? '')),
            'shift' => $this->cleanValue($this->firstValue($sourceRow, ['shift', 'シフト', '班']) ?: ($options['default_shift'] ?? '')),
            'gender' => $this->cleanValue($this->firstValue($sourceRow, ['gender', 'sex', '性別']) ?: ($options['default_gender'] ?? '')),
            'confidence' => $this->normalizeConfidence($this->firstValue($sourceRow, ['confidence', 'ocr_confidence', 'ocrConfidence', '信頼度'])),
        ];
    }

    private function canonicalGamesFromRow(array $sourceRow, array &$warnings, int $sourceRowNumber): array
    {
        $gameRows = $this->firstArrayValue($sourceRow, ['games', 'game_scores', 'gameScores', 'ゲーム']);
        if (is_array($gameRows)) {
            $games = [];
            foreach ($gameRows as $gameIndex => $gameRow) {
                if (! is_array($gameRow)) {
                    $warnings[] = sprintf('%d行目のゲーム%sは配列ではないためスキップしました。', $sourceRowNumber, (string) $gameIndex);
                    continue;
                }

                $games[] = $this->dropEmptyValues([
                    'game_number' => $this->intOrNull($this->firstValue($gameRow, ['game_number', 'game_no', 'game', 'g', 'gameNumber', 'ゲーム番号', 'ゲーム', 'G'])),
                    'score' => $this->intOrNull($this->firstValue($gameRow, ['score', 'pin', 'pins', 'total', 'スコア', '点数', '得点', 'ピン'])),
                    'confidence' => $this->normalizeConfidence($this->firstValue($gameRow, ['confidence', 'ocr_confidence', 'ocrConfidence', '信頼度'])),
                ]);
            }

            return $games;
        }

        $scoreRows = $this->firstArrayValue($sourceRow, ['scores', 'スコア']);
        if (is_array($scoreRows)) {
            $games = [];
            foreach ($scoreRows as $gameNumber => $score) {
                $normalizedGameNumber = $this->intOrNull($gameNumber) ?? $this->scoreGameNumberFromHeader($gameNumber);
                $games[] = $this->dropEmptyValues([
                    'game_number' => $normalizedGameNumber,
                    'score' => $this->intOrNull($score),
                ]);
            }

            return $games;
        }

        $games = [];
        foreach ($sourceRow as $key => $value) {
            $gameNumber = $this->scoreGameNumberFromHeader($key);
            if ($gameNumber !== null) {
                $score = $this->intOrNull($value);
                if ($score !== null) {
                    $games[] = [
                        'game_number' => $gameNumber,
                        'score' => $score,
                    ];
                }
            }
        }

        return $games;
    }

    private function parseMarkdownTable(array $lines): array
    {
        $table = [];
        foreach ($lines as $line) {
            if (! str_contains($line, '|')) {
                continue;
            }

            $trimmed = trim($line);
            $trimmed = trim($trimmed, '|');
            $cells = array_map(fn (string $cell): string => trim($cell), explode('|', $trimmed));
            if (! $this->isMarkdownSeparatorRow($cells)) {
                $table[] = $cells;
            }
        }

        return count($table) >= 2 ? $table : [];
    }

    private function parseDelimitedLines(array $lines): array
    {
        $table = [];
        foreach ($lines as $line) {
            $delimiter = $this->detectDelimiter($line);
            if ($delimiter === 'whitespace') {
                $cells = preg_split('/\s+/u', trim($line)) ?: [];
            } else {
                $cells = str_getcsv($line, $delimiter, '"', '');
            }

            $table[] = array_map(fn (mixed $cell): string => $this->cleanValue($cell), $cells);
        }

        return $table;
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

        $scoreColumn = $col(['score', 'スコア', '点数', '得点']);

        return [
            'license_number' => $col(['license_number', 'license_no', 'license', 'ライセンス番号', 'ライセンスNo', '会員番号']),
            'name' => $col(['name', 'player_name', '氏名', '名前', '選手名', '氏名漢字']),
            'entry_number' => $col(['entry_number', 'entry_no', 'entry', 'エントリー番号', '受付番号', '番号', 'no']),
            'stage' => $col(['stage', 'round', 'ステージ', 'ラウンド', '種目']),
            'shift' => $col(['shift', 'シフト', '班']),
            'gender' => $col(['gender', 'sex', '性別']),
            'game_number' => $col(['game_number', 'game_no', 'game', 'g', 'ゲーム番号', 'ゲーム', 'G']),
            'score' => $scoreColumn,
            'confidence' => $col(['confidence', 'ocr_confidence', '信頼度']),
            'score_columns' => $this->detectScoreColumns($header, $scoreColumn),
        ];
    }

    private function looksLikeHeader(array $cells): bool
    {
        $recognized = 0;
        foreach ($cells as $cell) {
            $key = $this->normalizeHeader($cell);
            if ($key === '') {
                continue;
            }

            if ($this->scoreGameNumberFromHeader($cell) !== null) {
                $recognized++;
                continue;
            }

            foreach ([
                'license_number', 'license_no', 'license', 'ライセンス番号', 'ライセンスNo', '会員番号',
                'name', 'player_name', '氏名', '名前', '選手名', '氏名漢字',
                'entry_number', 'entry_no', 'entry', 'エントリー番号', '受付番号', '番号', 'no',
                'stage', 'round', 'ステージ', 'ラウンド', '種目',
                'shift', 'シフト', '班', 'gender', 'sex', '性別',
                'game_number', 'game_no', 'game', 'g', 'ゲーム番号', 'ゲーム',
                'score', 'スコア', '点数', '得点', 'confidence', '信頼度',
            ] as $alias) {
                if ($key === $this->normalizeHeader($alias)) {
                    $recognized++;
                    break;
                }
            }
        }

        return $recognized >= 2;
    }

    private function detectScoreColumns(array $header, ?int $explicitScoreColumn): array
    {
        if ($explicitScoreColumn !== null) {
            return [];
        }

        $columns = [];
        $seenGameNumbers = [];
        foreach ($header as $index => $name) {
            $gameNumber = $this->scoreGameNumberFromHeader($name);
            if ($gameNumber === null || isset($seenGameNumbers[$gameNumber])) {
                continue;
            }

            $seenGameNumbers[$gameNumber] = true;
            $columns[] = [
                'index' => $index,
                'game_number' => $gameNumber,
            ];
        }

        return $columns;
    }

    private function scoreGameNumberFromHeader(mixed $header): ?int
    {
        $key = str_replace(['_', '-', '.', '/', '・', ' '], '', $this->normalizeHeader($header));
        $patterns = [
            '/^g(\d{1,2})$/u',
            '/^game(\d{1,2})$/u',
            '/^score(\d{1,2})$/u',
            '/^(\d{1,2})g$/u',
            '/^(\d{1,2})game$/u',
            '/^(\d{1,2})score$/u',
            '/^第?(\d{1,2})(?:g|ゲーム)$/u',
            '/^ゲーム(\d{1,2})$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $key, $matches) === 1) {
                $gameNumber = (int) $matches[1];

                return $gameNumber > 0 ? $gameNumber : null;
            }
        }

        return null;
    }

    private function extractGameScorePairs(string $line): array
    {
        $scores = [];
        $withoutPairs = preg_replace_callback(
            '/(?:第)?(\d{1,2})\s*(?:G|ゲーム|game)\s*[:：]?\s*(\d{1,3})/iu',
            function (array $matches) use (&$scores): string {
                $gameNumber = (int) $matches[1];
                $score = (int) $matches[2];
                if ($gameNumber > 0 && $score >= 0 && $score <= 300) {
                    $scores[(string) $gameNumber] = $score;
                }

                return ' ';
            },
            $line
        );

        return [
            'scores' => $scores,
            'text_without_pairs' => $withoutPairs ?? $line,
        ];
    }

    private function extractIdentityFromText(string $text): array
    {
        $text = $this->cleanValue($text);
        $license = '';
        if (preg_match('/\b([MF]?\d{4,}|[A-Z]\d{4,})\b/iu', $text, $matches) === 1) {
            $license = $this->compactValue($matches[1]);
            $text = trim(str_replace($matches[0], ' ', $text));
        }

        return [
            'license_number' => $license,
            'name' => preg_replace('/\s+/u', ' ', $text) ?: '',
        ];
    }

    private function removeScoresFromLine(string $line, array $scores): string
    {
        foreach ($scores as $score) {
            $line = preg_replace('/(?<!\d)' . preg_quote((string) $score, '/') . '(?!\d)/u', ' ', $line, 1) ?? $line;
        }

        return trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
    }

    private function decodeJsonLikeText(string $text): ?array
    {
        $candidates = [$text];

        if (preg_match('/```(?:json)?\s*(.*?)```/isu', $text, $matches) === 1) {
            $candidates[] = trim($matches[1]);
        }

        $firstObject = strpos($text, '{');
        $lastObject = strrpos($text, '}');
        if ($firstObject !== false && $lastObject !== false && $lastObject > $firstObject) {
            $candidates[] = substr($text, $firstObject, $lastObject - $firstObject + 1);
        }

        $firstArray = strpos($text, '[');
        $lastArray = strrpos($text, ']');
        if ($firstArray !== false && $lastArray !== false && $lastArray > $firstArray) {
            $candidates[] = substr($text, $firstArray, $lastArray - $firstArray + 1);
        }

        foreach ($candidates as $candidate) {
            $payload = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($payload)) {
                return $payload;
            }
        }

        return null;
    }

    private function firstValue(array $row, array $keys): mixed
    {
        $normalizedRow = [];
        foreach ($row as $key => $value) {
            $normalizedRow[$this->normalizeKey($key)] = $value;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }

            $normalizedKey = $this->normalizeKey($key);
            if (array_key_exists($normalizedKey, $normalizedRow)) {
                return $normalizedRow[$normalizedKey];
            }
        }

        return null;
    }

    private function firstArrayValue(array $row, array $keys): ?array
    {
        $value = $this->firstValue($row, $keys);

        return is_array($value) ? $value : null;
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    private function normalizeHeader(mixed $value): string
    {
        return mb_strtolower($this->identityKey($value), 'UTF-8');
    }

    private function normalizeKey(mixed $value): string
    {
        $value = $this->compactValue($value);
        $value = str_replace(['_', '-', '.', '/', '・'], '', $value);

        return mb_strtolower($value, 'UTF-8');
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
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
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

    private function value(array $values, ?int $index): string
    {
        if ($index === null || ! array_key_exists($index, $values)) {
            return '';
        }

        return (string) $values[$index];
    }

    private function detectDelimiter(string $line): string
    {
        if (str_contains($line, "\t")) {
            return "\t";
        }

        if (substr_count($line, ',') >= 2) {
            return ',';
        }

        return 'whitespace';
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

    private function isMarkdownSeparatorRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (! preg_match('/^\s*:?-{3,}:?\s*$/u', (string) $cell)) {
                return false;
            }
        }

        return ! empty($row);
    }

    private function dropEmptyValues(array $row): array
    {
        return array_filter($row, function (mixed $value): bool {
            if ($value === null) {
                return false;
            }

            if (is_string($value) && $value === '') {
                return false;
            }

            if (is_array($value) && empty($value)) {
                return false;
            }

            return true;
        });
    }
}
