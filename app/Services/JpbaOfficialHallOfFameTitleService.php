<?php

namespace App\Services;

use App\Models\ProBowler;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class JpbaOfficialHallOfFameTitleService
{
    public const INDEX_URL = 'https://www.jpba.or.jp/information/tournament/HallofFame.html';

    /**
     * @return array{pages:int,candidates:array<int,array<string,mixed>>,mismatches:array<int,array<string,mixed>>}
     */
    public function fetchCandidates(string $indexUrl = self::INDEX_URL): array
    {
        $urls = $this->parseProfileUrls($this->fetchHtml($indexUrl), $indexUrl);
        $candidates = [];
        $mismatches = [];

        foreach ($urls as $url) {
            $parsed = $this->parseProfile($this->fetchHtml($url), $url);
            if (! $parsed['pro_bowler_id']) {
                $mismatches[] = [
                    'source_url' => $url,
                    'name' => $parsed['name'],
                    'reason' => 'pro_bowler not resolved',
                ];

                continue;
            }

            if ($parsed['official_win_count'] !== count($parsed['candidates'])) {
                $mismatches[] = [
                    'source_url' => $url,
                    'name' => $parsed['name'],
                    'official_win_count' => $parsed['official_win_count'],
                    'candidate_count' => count($parsed['candidates']),
                    'reason' => 'Hall of Fame MainTitle count mismatch',
                ];

                continue;
            }

            $candidates = array_merge($candidates, $parsed['candidates']);
        }

        return [
            'pages' => count($urls),
            'candidates' => $candidates,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @return array<int,string>
     */
    public function parseProfileUrls(string $html, string $indexUrl): array
    {
        $html = $this->decodeHtml($html);
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/iu', $html, $matches);
        $urls = [];

        foreach ($matches[1] ?? [] as $href) {
            $url = $this->resolveUrl(html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8'), $indexUrl);
            if (preg_match('#/HallofFame/[^/]+\.html$#iu', $url) !== 1) {
                continue;
            }
            $urls[$url] = true;
        }

        return array_keys($urls);
    }

    /**
     * @return array{name:?string,pro_bowler_id:?int,official_win_count:?int,candidates:array<int,array<string,mixed>>}
     */
    public function parseProfile(string $html, string $url): array
    {
        $html = $this->decodeHtml($html);
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $lines = $this->htmlToLines($html);
        $heading = $dom->getElementsByTagName('h1')->item(0)?->textContent;
        $name = $this->japaneseName((string) $heading) ?? $this->nameFromLines($lines);
        $bowler = $this->resolveBowler($name);
        $officialWinCount = $this->officialWinCount($lines);
        $titles = $this->mainTitles($lines);
        $candidates = [];

        foreach ($titles as $title) {
            $payload = [
                'pro_bowler_id' => $bowler?->id,
                'license_no' => $bowler?->license_no,
                'license_no_num' => $bowler?->license_no_num,
                'name_kanji' => $bowler?->name_kanji ?: $name,
                'title_name' => $title['title_name'],
                'title_category' => 'normal',
                'year' => $title['year'],
                'won_date' => null,
                'venue_name' => null,
                'source_url' => $url,
                'source_result_url' => null,
                'source_label' => 'JPBA日本プロボウリング殿堂 MainTitle',
                'raw_text' => $title['raw_text'],
                'confidence' => 100,
                'status' => 'candidate',
                'error' => null,
            ];
            $payload['candidate_hash'] = sha1(implode('|', [
                $payload['license_no'] ?? '',
                $payload['license_no_num'] ?? '',
                $payload['title_name'],
                $payload['title_category'],
                $payload['year'],
                '',
                $payload['source_url'],
            ]));
            $candidates[] = $payload;
        }

        return [
            'name' => $name,
            'pro_bowler_id' => $bowler?->id,
            'official_win_count' => $officialWinCount,
            'candidates' => $candidates,
        ];
    }

    /**
     * @param  array<int,string>  $lines
     * @return array<int,array{year:int,title_name:string,raw_text:string}>
     */
    private function mainTitles(array $lines): array
    {
        $start = array_search('MainTitle', $lines, true);
        if ($start === false) {
            return [];
        }

        $year = null;
        $titles = [];
        foreach (array_slice($lines, $start + 1) as $line) {
            if (str_contains($line, 'PAGE TOP') || str_contains($line, 'プロボウリング殿堂')) {
                break;
            }

            if (preg_match('/^((?:19|20)\d{2})年\s*(.*)$/u', $line, $matches) === 1) {
                $year = (int) $matches[1];
                $line = $this->cleanText($matches[2]);
            }

            if (! $year || $line === '' || preg_match('/^(?:19|20)\d{2}年$/u', $line) === 1) {
                continue;
            }

            if (preg_match('/^[（(].+[）)]$/u', $line) === 1 && $titles !== []) {
                $lastIndex = array_key_last($titles);
                $titles[$lastIndex]['title_name'] = trim($titles[$lastIndex]['title_name'].' '.$line);
                $titles[$lastIndex]['raw_text'] = trim($titles[$lastIndex]['raw_text'].' '.$line);

                continue;
            }

            $titles[] = [
                'year' => $year,
                'title_name' => $line,
                'raw_text' => $year.'年 '.$line,
            ];
        }

        $unique = [];
        foreach ($titles as $title) {
            $key = $title['year'].'|'.mb_convert_kana($title['title_name'], 'asKV', 'UTF-8');
            $unique[$key] = $title;
        }

        return array_values($unique);
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function officialWinCount(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if (! str_contains($line, '優勝回数')) {
                continue;
            }

            $value = implode(' ', array_slice($lines, $index, 3));
            if (preg_match('/優勝回数\s*(\d+)回/u', mb_convert_kana($value, 'n', 'UTF-8'), $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function nameFromLines(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 120) as $line) {
            $name = $this->japaneseName($line);
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    private function japaneseName(string $heading): ?string
    {
        $heading = $this->cleanText($heading);
        if (preg_match('/^([\p{Han}\p{Hiragana}\p{Katakana}々ヶヵ\s　]+?)(?=\s+[A-Za-z])/u', $heading, $matches) === 1) {
            return $this->cleanText($matches[1]);
        }

        return null;
    }

    private function resolveBowler(?string $name): ?ProBowler
    {
        if (! $name) {
            return null;
        }

        $normalized = $this->normalizeName($name);

        return ProBowler::query()->get()->first(
            fn (ProBowler $bowler) => $this->normalizeName((string) $bowler->name_kanji) === $normalized
        );
    }

    /**
     * @return array<int,string>
     */
    private function htmlToLines(string $html): array
    {
        $html = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $html) ?: $html;
        $html = preg_replace('/<\s*\/?(?:p|div|tr|td|th|li|h[1-6]|section|dl|dt|dd)[^>]*>/iu', "\n", $html) ?: $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $lines = [];
        foreach (preg_split('/[\r\n]+/u', $text) ?: [] as $line) {
            $line = $this->cleanText($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function fetchHtml(string $url): string
    {
        $basename = basename((string) parse_url($url, PHP_URL_PATH));
        $cacheFile = storage_path('app/private/jpba-official-hall-of-fame/'.$basename);
        if (File::isFile($cacheFile)) {
            $cached = (string) File::get($cacheFile);
            if ($cached !== '') {
                return $cached;
            }
        }

        $response = Http::connectTimeout(10)
            ->timeout(20)
            ->retry(2, 1000)
            ->withoutVerifying()
            ->withHeaders(['User-Agent' => 'JPBA-SYSTEM Hall of Fame title import'])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('JPBA Hall of Fame HTTP status '.$response->status());
        }

        $html = (string) $response->body();
        File::ensureDirectoryExists(dirname($cacheFile));
        $written = File::put($cacheFile, $html, true);
        clearstatcache(true, $cacheFile);
        if ($written === false || ! File::isFile($cacheFile) || File::size($cacheFile) !== strlen($html)) {
            throw new RuntimeException('JPBA Hall of Fame cache write failed: '.$basename);
        }

        return $html;
    }

    private function decodeHtml(string $html): string
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'EUC-JP', 'ISO-2022-JP'], true) ?: 'UTF-8';

        return $encoding === 'UTF-8' ? $html : mb_convert_encoding($html, 'UTF-8', $encoding);
    }

    private function resolveUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        if (str_starts_with($href, '/')) {
            return 'https://www.jpba.or.jp'.$href;
        }

        return preg_replace('/[^\/]+$/', '', $baseUrl).$href;
    }

    private function normalizeName(string $name): string
    {
        $name = str_replace([' ', '　'], '', trim($name));
        $name = str_replace(['髙', '﨑', '濵', '邊', '邉'], ['高', '崎', '濱', '辺', '辺'], $name);

        return mb_convert_kana($name, 'asKV', 'UTF-8');
    }

    private function cleanText(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        $value = str_replace(["\r\n", "\r", "\n", "\t", "\xc2\xa0"], ' ', $value);
        $value = preg_replace('/[　 ]+/u', ' ', $value) ?: $value;

        return trim($value);
    }
}
