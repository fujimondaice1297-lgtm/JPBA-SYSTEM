<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportProBowlersFromCsv extends Command
{
    protected $signature = 'import:pro_bowlers_csv
        {--path= : base_path からの相対パス（未指定なら OLD_JPBA/csv/Pro_colum.csv）}
        {--probe : 診断だけして終了（インポートしない）}
        {--scan-rows=80 : 自動判定に使う走査行数（デフォルト80）}
        {--license-index= : ライセンス列を手動指定（0始まりの列index）}';

    protected $description = 'CSVファイルからプロボウラー情報をインポート（日本語ヘッダ対応・列順変化OK・ライセンス列自動検出・形式検証あり）';

    private ?string $currentSchema = null;

    public function handle(): int
    {
        $rel  = $this->option('path') ?: 'OLD_JPBA/csv/Pro_colum.csv';
        $path = base_path($rel);

        if (!file_exists($path)) {
            $this->error("CSVファイルが見つかりません: {$path}");
            return 1;
        }

        $h = fopen($path, 'r');
        if (!$h) {
            $this->error('CSVファイルを開けませんでした。');
            return 1;
        }

        // 区切り文字自動判定
        $firstLine = fgets($h);
        if ($firstLine === false) {
            $this->error('CSVが空です。');
            fclose($h);
            return 1;
        }
        $delimiter = $this->detectDelimiterFromLine($firstLine);
        rewind($h);

        // ヘッダ
        $headerRaw = fgetcsv($h, 0, $delimiter);
        if ($headerRaw === false) {
            $this->error('CSVヘッダを読めませんでした。');
            fclose($h);
            return 1;
        }

        $header = $this->normalizeRow($headerRaw);
        $map    = $this->buildHeaderMap($header);
        $posAfterHeader = ftell($h);

        // ヘッダからの推定（当たればラッキー）
        $idxByHeader = $this->idx($map, [
            'ライセンスNo', 'ライセンス番号', '会員番号', '会員No', 'LICENSE_NO'
        ]);

        // データから候補列をランキング
        $scanRows = (int)($this->option('scan-rows') ?? 80);
        if ($scanRows <= 0) $scanRows = 80;

        $cand = $this->collectLicenseCandidatesFromData(
            $h,
            $delimiter,
            $posAfterHeader,
            $scanRows,
            count($header),
            $idxByHeader
        );

        // 手動指定
        $manual = $this->option('license-index');
        $idxManual = null;
        if ($manual !== null && $manual !== '') {
            $idxManual = is_numeric($manual) ? (int)$manual : null;
        }

        $idxAuto  = $cand['best_index'];
        $idxFinal = $idxByHeader;

        if ($idxManual !== null) {
            $idxFinal = $idxManual;
        } else {
            if ($idxByHeader === null) {
                $idxFinal = $idxAuto;
            } else {
                // ヘッダ推定列が空なら自動に切替
                if (($cand['header_non_empty'] === 0 && $cand['best_non_empty'] > 0) ||
                    ($cand['header_matches'] === 0 && $cand['best_matches'] >= 5)) {
                    $idxFinal = $idxAuto;
                }
            }
        }

        // probe（診断のみ）
        if ($this->option('probe')) {
            $this->info('=== PROBE（診断のみ）===');
            $this->line("path: {$rel}");
            $this->line('delimiter: ' . $this->delimiterLabel($delimiter));
            $this->line('columns(header): ' . count($header));

            $this->line('idx_by_header: ' . ($idxByHeader === null ? 'null' : (string)$idxByHeader)
                . ' / label: ' . ($idxByHeader !== null ? ($header[$idxByHeader] ?? 'N/A') : 'N/A'));

            $this->line('idx_auto: ' . ($idxAuto === null ? 'null' : (string)$idxAuto)
                . " / best_matches={$cand['best_matches']} / best_unique={$cand['best_unique']} / best_non_empty={$cand['best_non_empty']}");

            if ($idxByHeader !== null) {
                $this->line("header_col_non_empty={$cand['header_non_empty']} / header_col_matches={$cand['header_matches']}");
            }

            if ($idxManual !== null) {
                $this->line("idx_manual: {$idxManual}");
            }

            $this->line('sample_best: ' . implode(' | ', $cand['best_samples']));
            $this->line('sample_header: ' . implode(' | ', $cand['header_samples']));

            $this->line('');
            $this->info('--- TOP CANDIDATES (by data) ---');
            foreach ($cand['top_candidates'] as $c) {
                $label = $header[$c['index']] ?? 'N/A';
                $this->line(sprintf(
                    'idx=%d label=%s matches=%d unique=%d non_empty=%d samples=%s',
                    $c['index'],
                    $label,
                    $c['matches'],
                    $c['unique'],
                    $c['non_empty'],
                    implode('|', $c['samples'])
                ));
            }

            fclose($h);
            return 0;
        }

        if ($idxFinal === null) {
            $this->error('ライセンス列を特定できません（ヘッダでもデータでも検出不可）。CSVがデータ本体か確認してください。');
            fclose($h);
            return 1;
        }

        // DB列名のゆらぎ吸収
        $colNameKanji = $this->firstExistingColumn('pro_bowlers', ['name_kanji', 'name']);
        $colNameKana  = $this->firstExistingColumn('pro_bowlers', ['name_kana', 'kana']);
        $colBirth     = $this->firstExistingColumn('pro_bowlers', ['birthdate']);
        $colSex       = $this->firstExistingColumn('pro_bowlers', ['sex_id', 'sex']);

        // インポート開始：ヘッダ直後へ戻す
        fseek($h, $posAfterHeader);

        $imported = 0;
        $skippedBlank = 0;
        $skippedInvalid = 0;
        $failed = 0;

        while (($rowRaw = fgetcsv($h, 0, $delimiter)) !== false) {
            $row = $this->normalizeRow($rowRaw);

            if (count(array_filter($row, fn($v) => $v !== '')) === 0) {
                continue;
            }

            $licenseNo = strtoupper(trim((string)($row[$idxFinal] ?? '')));

            if ($licenseNo === '' || in_array($licenseNo, ['ライセンスNO', 'ライセンス番号', '会員番号', '会員NO'], true)) {
                $skippedBlank++;
                continue;
            }

            // ★重要：形式チェック（郵便番号 099-0403 等はここで弾く）
            if (!$this->looksLikeLicense($licenseNo)) {
                $skippedInvalid++;
                continue;
            }

            $attrs  = ['license_no' => $licenseNo];
            $values = [];

            // 氏名/カナ（ヘッダから取れた時だけ）
            $idxName = $this->idx($map, ['氏名', '名前', 'NAME']);
            $idxKana = $this->idx($map, ['名前（フリガナ）', '名前(フリガナ)', 'フリガナ', 'カナ', 'KANA']);

            if ($colNameKanji && $idxName !== null && array_key_exists($idxName, $row)) {
                $v = trim((string)($row[$idxName] ?? ''));
                if ($v !== '') $values[$colNameKanji] = $v;
            }
            if ($colNameKana && $idxKana !== null && array_key_exists($idxKana, $row)) {
                $v = trim((string)($row[$idxKana] ?? ''));
                if ($v !== '') $values[$colNameKana] = $v;
            }

            // 性別
            $idxSex = $this->idx($map, ['性別', 'SEX']);
            if ($colSex) {
                $sexStr = ($idxSex !== null && array_key_exists($idxSex, $row)) ? (string)($row[$idxSex] ?? '') : '';
                $values[$colSex] = $this->parseSex($sexStr);
            }

            // 生年月日
            $idxBirth = $this->idx($map, ['生年月日', '生年 月日', '誕生日', '生年月日(西暦)']);
            if ($colBirth) {
                $birth = null;
                if ($idxBirth !== null && array_key_exists($idxBirth, $row)) {
                    $birth = $this->tryParseDate((string)($row[$idxBirth] ?? ''));
                }
                if ($birth) {
                    $values[$colBirth] = $birth;
                } else {
                    if (!$this->isNullable('pro_bowlers', $colBirth)) {
                        $values[$colBirth] = Carbon::create(1970, 1, 1, 0, 0, 0);
                    }
                }
            }

            try {
                ProBowler::updateOrCreate($attrs, $values);
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                if ($failed <= 3) {
                    $this->error("DB更新失敗: license_no={$licenseNo} / " . $e->getMessage());
                }
            }
        }

        fclose($h);

        $this->info("CSVインポート完了: imported={$imported}, skipped_blank={$skippedBlank}, skipped_invalid={$skippedInvalid}, failed={$failed}");
        return 0;
    }

    private function normalizeRow(array $row): array
    {
        return array_map(function ($v) {
            $v = (string)($v ?? '');
            $v = preg_replace('/^\xEF\xBB\xBF/', '', $v);

            if (function_exists('mb_convert_encoding')) {
                $v = mb_convert_encoding($v, 'UTF-8', 'SJIS-win,UTF-8');
            }

            return trim($v);
        }, $row);
    }

    private function buildHeaderMap(array $header): array
    {
        $map = [];
        foreach ($header as $i => $h) {
            $k = $this->normHeader($h);
            if ($k !== '' && !array_key_exists($k, $map)) {
                $map[$k] = $i;
            }
        }
        return $map;
    }

    private function idx(array $map, array $keys): ?int
    {
        foreach ($keys as $k) {
            $nk = $this->normHeader($k);
            if (array_key_exists($nk, $map)) return $map[$nk];
        }
        return null;
    }

    private function normHeader(string $s): string
    {
        $s = trim($s);

        if (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'SJIS-win,UTF-8');
        }
        if (function_exists('mb_convert_kana')) {
            $s = mb_convert_kana($s, 'asKV', 'UTF-8');
        }

        $s = preg_replace('/\s+/u', '', $s);
        $s = str_replace(['．', '.', '・', '：', ':', '-', '_', '／', '/', '（', '）', '(', ')', '【', '】', '[', ']', '「', '」', '"', "'"], '', $s);
        $s = preg_replace('/\bNO\b/i', 'No', $s);

        return $s;
    }

    private function collectLicenseCandidatesFromData($h, string $delimiter, int $posAfterHeader, int $scanRows, int $headerCols, ?int $idxByHeader): array
    {
        fseek($h, $posAfterHeader);

        $nonEmpty = array_fill(0, $headerCols, 0);
        $matches  = array_fill(0, $headerCols, 0);
        $unique   = array_fill(0, $headerCols, 0);
        $uniqSets = array_fill(0, $headerCols, []);
        $samples  = array_fill(0, $headerCols, []);

        $headerNonEmpty = 0;
        $headerMatches  = 0;
        $headerSamples  = [];

        $rows = 0;
        while ($rows < $scanRows && ($rowRaw = fgetcsv($h, 0, $delimiter)) !== false) {
            $row = $this->normalizeRow($rowRaw);

            if (count(array_filter($row, fn($v) => $v !== '')) === 0) {
                continue;
            }

            for ($i = 0; $i < $headerCols; $i++) {
                $v = strtoupper(trim((string)($row[$i] ?? '')));
                if ($v === '') continue;

                $nonEmpty[$i]++;

                if ($this->looksLikeLicense($v)) {
                    $matches[$i]++;
                    if (!isset($uniqSets[$i][$v])) {
                        $uniqSets[$i][$v] = true;
                        $unique[$i]++;
                    }
                    if (count($samples[$i]) < 5) {
                        $samples[$i][] = $v;
                    }
                }
            }

            if ($idxByHeader !== null) {
                $v = strtoupper(trim((string)($row[$idxByHeader] ?? '')));
                if ($v !== '') $headerNonEmpty++;
                if ($v !== '' && $this->looksLikeLicense($v)) $headerMatches++;
                if (count($headerSamples) < 5) $headerSamples[] = ($v === '' ? '(empty)' : $v);
            }

            $rows++;
        }

        $cand = [];
        for ($i = 0; $i < $headerCols; $i++) {
            if ($matches[$i] <= 0) continue;
            $score = ($matches[$i] * 100000) + ($unique[$i] * 1000) + $nonEmpty[$i];
            $cand[] = [
                'index' => $i,
                'score' => $score,
                'matches' => $matches[$i],
                'unique' => $unique[$i],
                'non_empty' => $nonEmpty[$i],
                'samples' => $samples[$i],
            ];
        }

        usort($cand, fn($a, $b) => $b['score'] <=> $a['score']);
        $best = $cand[0] ?? null;

        fseek($h, $posAfterHeader);

        return [
            'best_index' => $best['index'] ?? null,
            'best_matches' => $best['matches'] ?? 0,
            'best_unique' => $best['unique'] ?? 0,
            'best_non_empty' => $best['non_empty'] ?? 0,
            'best_samples' => $best['samples'] ?? [],
            'header_non_empty' => $headerNonEmpty,
            'header_matches' => $headerMatches,
            'header_samples' => $headerSamples,
            'top_candidates' => array_slice($cand, 0, 10),
        ];
    }

    private function looksLikeLicense(string $v): bool
    {
        $v = strtoupper(trim($v));
        if ($v === '') return false;

        // 例: F00000001（英字 + 7〜10桁）
        if (preg_match('/^[A-Z][0-9]{7,10}$/', $v)) return true;

        // 例: M0000P014（英字 + 4桁 + 英字 + 3〜4桁）
        if (preg_match('/^[A-Z][0-9]{4}[A-Z][0-9]{3,4}$/', $v)) return true;

        // 郵便番号 099-0403 など（ハイフン含む）は false に落ちる
        return false;
    }

    private function tryParseDate(string $s): ?Carbon
    {
        $s = trim($s);
        if ($s === '') return null;

        if (preg_match('/^\d{4}$/', $s)) {
            try { return Carbon::create((int)$s, 1, 1, 0, 0, 0); } catch (\Throwable $e) { return null; }
        }

        $s = str_replace(['年', '月', '日'], ['-', '-', ''], $s);
        $s = str_replace('/', '-', $s);

        try { return Carbon::parse($s); } catch (\Throwable $e) { return null; }
    }

    private function parseSex(string $s): int
    {
        $s = trim($s);
        if ($s === '') return 0;

        if ($s === '1') return 1;
        if ($s === '2') return 2;

        if (str_contains($s, '男')) return 1;
        if (str_contains($s, '女')) return 2;

        return 0;
    }

    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (Schema::hasColumn($table, $c)) return $c;
        }
        return null;
    }

    private function isNullable(string $table, string $column): bool
    {
        $schema = $this->getCurrentSchema();

        $row = DB::selectOne(
            'select is_nullable from information_schema.columns where table_schema = ? and table_name = ? and column_name = ? limit 1',
            [$schema, $table, $column]
        );

        if (!$row || !isset($row->is_nullable)) return true;
        return strtoupper((string)($row->is_nullable)) === 'YES';
    }

    private function getCurrentSchema(): string
    {
        if ($this->currentSchema !== null) return $this->currentSchema;

        $row = DB::selectOne('select current_schema() as s');
        $this->currentSchema = ($row && isset($row->s)) ? (string)$row->s : 'public';
        return $this->currentSchema;
    }

    private function detectDelimiterFromLine(string $line): string
    {
        $counts = [
            ','  => substr_count($line, ','),
            ';'  => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);
        $best = array_key_first($counts);
        return ($counts[$best] ?? 0) > 0 ? $best : ',';
    }

    private function delimiterLabel(string $d): string
    {
        return $d === "\t" ? 'TAB(\t)' : $d;
    }
}

