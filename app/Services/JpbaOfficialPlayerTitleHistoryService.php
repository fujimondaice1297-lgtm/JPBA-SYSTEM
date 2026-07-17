<?php

namespace App\Services;

use App\Models\ProBowler;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class JpbaOfficialPlayerTitleHistoryService
{
    public const BASE_URL = 'https://www.jpba1.jp';

    private int $connectTimeout = 20;

    private int $requestAttempts = 3;

    public function configureNetwork(int $connectTimeout, int $requestAttempts): self
    {
        $this->connectTimeout = max(3, min(30, $connectTimeout));
        $this->requestAttempts = max(1, min(5, $requestAttempts));

        return $this;
    }

    /**
     * @return array<int,string>
     */
    public function fetchYearUrls(string $licenseNo, bool $refresh = false): array
    {
        $licenseNo = $this->normalizeLicense($licenseNo);
        $url = self::BASE_URL.'/player1/detail.html?id='.rawurlencode($licenseNo);

        return $this->parseYearUrls($this->fetchHtml($url, $licenseNo.'/index.html', $refresh), $licenseNo);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchWinsForYear(ProBowler $bowler, int $year, string $url, bool $refresh = false): array
    {
        $licenseNo = $this->normalizeLicense((string) $bowler->license_no);

        return $this->parseWinsForYear(
            $this->fetchHtml($url, $licenseNo.'/'.$year.'.html', $refresh),
            $bowler,
            $year,
            $url
        );
    }

    /**
     * @param  array<int,string>  $yearUrls
     * @return array<int,array<string,mixed>>
     */
    public function fetchWinsForYears(
        ProBowler $bowler,
        array $yearUrls,
        int $pauseMs = 250,
        bool $refresh = false
    ): array {
        $wins = [];
        foreach ($yearUrls as $year => $url) {
            $wins = array_merge($wins, $this->fetchWinsForYear($bowler, (int) $year, $url, $refresh));

            if ($pauseMs > 0) {
                usleep($pauseMs * 1000);
            }
        }

        return array_values(collect($wins)->unique('candidate_hash')->all());
    }

    /**
     * @return array<int,string>
     */
    public function parseYearUrls(string $html, string $licenseNo): array
    {
        $licenseNo = $this->normalizeLicense($licenseNo);
        $dom = $this->dom($html);
        $urls = [];

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = html_entity_decode((string) $anchor->getAttribute('href'), ENT_QUOTES, 'UTF-8');
            if ($href === '') {
                continue;
            }

            $query = parse_url($href, PHP_URL_QUERY);
            if (! is_string($query)) {
                continue;
            }

            parse_str($query, $params);
            $linkLicense = $this->normalizeLicense((string) ($params['id'] ?? ''));
            $year = filter_var($params['year'] ?? null, FILTER_VALIDATE_INT);
            if ($linkLicense !== $licenseNo || ! $year || $year < 1900 || $year > 2100) {
                continue;
            }

            $urls[(int) $year] = $this->resolveUrl($href);
        }

        krsort($urls);

        return $urls;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function parseWinsForYear(
        string $html,
        ProBowler $bowler,
        int $selectedYear,
        string $sourceUrl
    ): array {
        $dom = $this->dom($html);
        $wins = [];

        foreach ($dom->getElementsByTagName('table') as $table) {
            $rows = $this->tableRows($table);
            if (count($rows) < 2) {
                continue;
            }

            $headerIndex = null;
            $indexes = [];
            foreach ($rows as $rowIndex => $row) {
                $indexes = array_flip($row);
                if (isset($indexes['開催年'], $indexes['開催日'], $indexes['大会名'], $indexes['順位'])) {
                    $headerIndex = $rowIndex;
                    break;
                }
            }

            if ($headerIndex === null) {
                continue;
            }

            foreach (array_slice($rows, $headerIndex + 1) as $row) {
                $rank = $this->compactText($row[$indexes['順位']] ?? '');
                if ($rank !== '1位') {
                    continue;
                }

                $titleName = $this->cleanText($row[$indexes['大会名']] ?? '');
                if ($titleName === '' || ! $this->isTitleEvent($titleName)) {
                    continue;
                }

                $year = $this->yearValue($row[$indexes['開催年']] ?? '', $selectedYear);
                $wonDate = $this->dateValue($row[$indexes['開催日']] ?? '', $year);
                $category = $this->titleCategory($titleName);
                $canonicalSourceUrl = preg_replace('/#.*$/', '', $sourceUrl).'#entry';
                $payload = [
                    'pro_bowler_id' => $bowler->getKey(),
                    'license_no' => $this->normalizeLicense((string) $bowler->license_no),
                    'license_no_num' => $bowler->license_no_num,
                    'name_kanji' => $bowler->name_kanji,
                    'title_name' => $titleName,
                    'title_category' => $category,
                    'year' => $year,
                    'won_date' => $wonDate,
                    'venue_name' => null,
                    'source_url' => $canonicalSourceUrl,
                    'source_result_url' => $canonicalSourceUrl,
                    'source_label' => 'JPBA選手データ '.$year.'年度大会成績',
                    'raw_text' => implode(' | ', $row),
                    'confidence' => 100,
                    'status' => 'candidate',
                    'error' => null,
                ];
                $payload['candidate_hash'] = $this->candidateHash($payload);

                $wins[$payload['candidate_hash']] = $payload;
            }
        }

        return array_values($wins);
    }

    public function titleCategory(string $titleName): string
    {
        $normalized = $this->normalizeTitleText($titleName);
        $normalized = preg_replace('/[\s　]+/u', '', $normalized) ?: $normalized;

        return str_contains($normalized, 'シーズントライアル')
            || str_contains($normalized, 'SEASONTRIAL')
            || preg_match('/^ST(?:ウィンター|スプリング|サマー|オータム)(?:シリーズ)?[A-D]?$/u', $normalized) === 1
                ? 'season_trial'
                : 'normal';
    }

    public function titleFingerprint(string $titleName): string
    {
        $value = $this->normalizeTitleText($titleName);
        $value = preg_replace(
            '/^H\.C\s*第47回全日本女子プロ$/u',
            'HANDA CUP 第47回全日本女子プロボウリング選手権大会',
            $value
        ) ?: $value;
        $value = preg_replace('/^(?:R1|ROUND1)\s*GCS?B/u', 'ROUND1 GRAND CHAMPIONSHIP BOWLING', $value) ?: $value;
        $value = preg_replace('/^((?:第\d+回)?)HC(?=プロボウリングマスターズ)/u', '$1HANDACUP', $value) ?: $value;
        $value = str_replace(['JPBA', '公益社団法人日本プロボウリング協会'], '', $value);
        $value = preg_replace('/^創立50周年記念(?:大会|レギュラーの部)$/u', '創立50周年記念', $value) ?: $value;
        $value = preg_replace('/決勝大会\s*R$/u', '決勝大会', $value) ?: $value;
        $value = preg_replace('/FINAL\s*R部門$/u', 'FINAL', $value) ?: $value;
        $value = str_replace('レディース新人戦', '女子新人戦', $value);
        $value = preg_replace('/(?:19|20)\d{2}/u', '', $value) ?: $value;
        $value = str_replace(['プロボウリング', 'ボウリングトーナメント', 'ボウリング', 'カップ'], '', $value);
        if (str_contains($value, 'ジャパンオープン')) {
            $value = str_replace(['STORM', '選手権大会', '選手権'], '', $value);
        }
        $value = preg_replace('/[\p{Z}\p{P}\p{S}_]+/u', '', $value) ?: $value;
        $value = str_replace('JLBCクイーンズオープンプリンス', 'JLBCプリンス', $value);
        $value = preg_replace('/^コカコーラ(?:千葉オープン女子)?$/u', 'コカコーラ', $value) ?: $value;

        return trim($value);
    }

    public function titleDisplayName(string $titleName, ?int $year = null): string
    {
        $normalized = $this->normalizeTitleText($titleName);
        if ($year === 2015 && preg_match('/^H\.C\s*第47回全日本女子プロ$/u', $normalized) === 1) {
            return 'HANDA CUP 第47回全日本女子プロボウリング選手権大会';
        }

        if ($this->titleCategory($titleName) !== 'season_trial') {
            return trim($titleName);
        }

        $value = $normalized;
        $value = preg_replace('/^[\'’‘]?\d{2}(?=シーズントライアル)/u', '', $value) ?: $value;
        $value = preg_replace('/^(?:JPBA)?シーズントライアル/u', 'JPBAシーズントライアル', $value) ?: $value;
        if ($year !== null && preg_match('/(?:19|20)\d{2}/u', $value) !== 1) {
            $value = preg_replace('/^JPBAシーズントライアル/u', 'JPBAシーズントライアル'.$year, $value) ?: $value;
        }
        $value = preg_replace('/^(JPBAシーズントライアル(?:19|20)\d{2})\s*/u', '$1 ', $value) ?: $value;
        $value = preg_replace('/(ウィンター|スプリング|サマー|オータム)$/u', '$1シリーズ', $value) ?: $value;

        return trim($value);
    }

    private function isTitleEvent(string $titleName): bool
    {
        if ($this->titleCategory($titleName) === 'season_trial') {
            return true;
        }

        $normalized = preg_replace('/[\s　]+/u', '', $this->normalizeTitleText($titleName)) ?: $titleName;

        if (preg_match('/(?:JPBA)?プレイヤーズドリームマッチ2022[A-Z]$/u', $normalized) === 1) {
            return false;
        }

        if (preg_match('/^(?:R1|ROUND1)GCB2018R(?:部門)?$/u', $normalized) === 1
            || (str_contains($normalized, 'ROUND1GRANDCHAMPIONSHIPBOWLING2018')
                && str_contains($normalized, '三団体グランドチャンピオン大会'))) {
            return false;
        }

        if (preg_match('/^第(\d+)回全日本ミックス(?:ダブルス)?$/u', $normalized, $matches) === 1
            && (int) $matches[1] < 30) {
            return false;
        }

        return preg_match('/(?:^順位(?:決定)?戦$|選抜|予選|出場優先順位(?:決定)?戦|(?:上|下)半期(?:男子|女子)?順位(?:決定)?戦|オールエベンツ|ALLEVENTS)/u', $normalized) !== 1;
    }

    private function normalizeTitleText(string $titleName): string
    {
        $value = mb_strtoupper(mb_convert_kana($titleName, 'asKV', 'UTF-8'), 'UTF-8');
        $value = str_replace('シーズントライラウ', 'シーズントライアル', $value);
        $value = preg_replace('/シーズン(?:T|Ｔ)/u', 'シーズントライアル', $value) ?: $value;
        $value = preg_replace('/\bST(?=(?:19|20)\d{2})/u', 'シーズントライアル', $value) ?: $value;
        $value = preg_replace('/^ST(?=(?:ウィンター|スプリング|サマー|オータム))/u', 'シーズントライアル', $value) ?: $value;
        $value = preg_replace(
            '/(ウィンター|スプリング|サマー|オータム)\s*S(?:\s*[A-D]\s*会場?)?$/u',
            '$1シリーズ',
            $value
        ) ?: $value;
        $value = preg_replace('/(シーズントライアル.*シリーズ)\s*[A-D]\s*(?:会場)?$/u', '$1', $value) ?: $value;

        return $value;
    }

    private function fetchHtml(string $url, string $cacheKey, bool $refresh): string
    {
        $cacheFile = storage_path('app/private/jpba-official-player-history/'.str_replace('/', DIRECTORY_SEPARATOR, $cacheKey));
        if (! $refresh && File::isFile($cacheFile)) {
            $cached = (string) File::get($cacheFile);
            if (str_contains($cached, 'player-detail')) {
                return $cached;
            }
        }

        $response = Http::connectTimeout($this->connectTimeout)
            ->timeout(max(15, $this->connectTimeout + 10))
            ->retry($this->requestAttempts, 1500)
            ->withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'JPBA-SYSTEM official player title history import',
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Official player history HTTP status '.$response->status());
        }

        $html = (string) $response->body();
        if (! str_contains($html, 'player-detail')) {
            throw new RuntimeException('Official player history body did not contain player-detail');
        }

        File::ensureDirectoryExists(dirname($cacheFile));
        $written = File::put($cacheFile, $html, true);
        clearstatcache(true, $cacheFile);
        if ($written === false || ! File::isFile($cacheFile) || File::size($cacheFile) !== strlen($html)) {
            throw new RuntimeException('Official player history cache write failed: '.$cacheKey);
        }

        return $html;
    }

    private function dom(string $html): \DOMDocument
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'EUC-JP', 'ISO-2022-JP'], true) ?: 'UTF-8';
        if ($encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * @return array<int,array<int,string>>
     */
    private function tableRows(\DOMElement $table): array
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $child) {
                if (! $child instanceof \DOMElement || ! in_array($child->tagName, ['th', 'td'], true)) {
                    continue;
                }
                $cells[] = $this->cleanText($child->textContent);
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    private function yearValue(string $value, int $fallback): int
    {
        $value = mb_convert_kana($value, 'n', 'UTF-8');

        return preg_match('/(19|20)\d{2}/u', $value, $matches) === 1
            ? (int) $matches[0]
            : $fallback;
    }

    private function dateValue(string $value, int $year): ?string
    {
        $value = mb_convert_kana($value, 'n', 'UTF-8');
        if (preg_match('/(\d{1,2})\s*[\/\.\-]\s*(\d{1,2})/u', $value, $matches) !== 1) {
            return null;
        }

        if (! checkdate((int) $matches[1], (int) $matches[2], $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, (int) $matches[1], (int) $matches[2]);
    }

    private function resolveUrl(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return rtrim(self::BASE_URL, '/').'/'.ltrim($href, '/');
    }

    private function normalizeLicense(string $licenseNo): string
    {
        $licenseNo = strtoupper(trim($licenseNo));
        if (preg_match('/^([MF])(\d+)$/', $licenseNo, $matches) === 1) {
            return $matches[1].str_pad((string) ((int) $matches[2]), 8, '0', STR_PAD_LEFT);
        }

        return $licenseNo;
    }

    private function cleanText(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        $value = str_replace(["\r\n", "\r", "\n", "\t", "\xc2\xa0"], ' ', $value);
        $value = preg_replace('/[　 ]+/u', ' ', $value) ?: $value;

        return trim($value);
    }

    private function compactText(?string $value): string
    {
        return preg_replace('/[\s　]+/u', '', mb_convert_kana($this->cleanText($value), 'n', 'UTF-8')) ?: '';
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function candidateHash(array $payload): string
    {
        return sha1(implode('|', [
            $payload['license_no'] ?? '',
            $payload['license_no_num'] ?? '',
            $payload['title_name'] ?? '',
            $payload['title_category'] ?? '',
            $payload['year'] ?? '',
            $payload['won_date'] ?? '',
            $payload['source_url'] ?? '',
        ]));
    }
}
