<?php

namespace App\Services;

use App\Models\ProBowler;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class JpbaOfficialTitleCandidateService
{
    public const BASE_URL = 'https://www.jpba.or.jp';

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchCandidates(string $url): array
    {
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'JPBA-SYSTEM official title candidate import',
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Official tournament HTTP status '.$response->status());
        }

        return $this->parseCandidates((string) $response->body(), $url);
    }

    /**
     * @return array<int,string>
     */
    public function discoverSeasonTrialUrls(string $indexUrl): array
    {
        return $this->discoverTournamentUrls($indexUrl, true);
    }

    /**
     * @return array<int,string>
     */
    public function discoverOfficialTournamentUrls(string $indexUrl): array
    {
        return $this->discoverTournamentUrls($indexUrl, false);
    }

    /**
     * @return array<int,string>
     */
    private function discoverTournamentUrls(string $indexUrl, bool $seasonTrialsOnly): array
    {
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'JPBA-SYSTEM official title candidate import',
            ])
            ->get($indexUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Official tournament index HTTP status '.$response->status());
        }

        $html = $this->decodeHtml((string) $response->body());
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        $urls = [];
        foreach ($matches as $match) {
            $href = html_entity_decode((string) $match[1], ENT_QUOTES, 'UTF-8');
            $text = $this->cleanText(strip_tags((string) $match[2]));
            $haystack = strtolower($href.' '.$text);
            $url = $this->resolveUrl($href, $indexUrl);
            if (! $this->looksLikeOfficialTournamentPageUrl($url)) {
                continue;
            }

            $isSeasonTrial = $this->looksLikeSeasonTrialLink($haystack, $text);
            if ($seasonTrialsOnly !== $isSeasonTrial) {
                continue;
            }
            if (! $seasonTrialsOnly && $this->looksLikeNonTitleTournamentLink($url, $text)) {
                continue;
            }

            $urls[$url] = true;
        }

        return array_values(array_keys($urls));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function parseCandidates(string $html, string $url): array
    {
        $html = $this->decodeHtml($html);
        $lines = $this->htmlToLines($html);
        $titleName = $this->extractTitleName($html, $lines);
        if ($this->isExplicitNonTitleTournament($url, $titleName)) {
            return [];
        }

        $year = $this->extractYearFromUrl($url) ?? $this->extractYear($titleName, $lines);
        $category = $this->titleCategory($titleName, $lines);
        $venueDates = $this->extractVenueDates($lines, $year);

        if ($category !== 'season_trial') {
            return $this->parseOfficialTournamentCandidates($lines, $url, $titleName, $year);
        }

        $candidates = [];
        foreach ($lines as $index => $line) {
            if ($line === '' || ! str_contains($line, '優勝') || ! str_contains($line, '会場')) {
                continue;
            }
            if (str_contains($line, '優勝者')) {
                continue;
            }

            $winnerText = $this->nextWinnerText($lines, $index);
            if ($winnerText === null) {
                continue;
            }

            $licenseNumber = $this->licenseNumber($winnerText);
            $name = $this->winnerName($winnerText);
            if ($name === null || $this->looksLikeInvalidWinnerName($name)) {
                $name = $this->winnerNameFromVenueLine($line);
            }
            if ($licenseNumber === null || $name === null) {
                continue;
            }

            $bowler = $this->resolveBowler($licenseNumber, $name, $lines);
            $venueName = $this->venueNameFromWinnerLine($line);
            $wonDate = $this->dateForVenue($venueName, $venueDates);

            $payload = [
                'pro_bowler_id' => $bowler?->id,
                'license_no' => $bowler?->license_no,
                'license_no_num' => $bowler?->license_no_num ?: $licenseNumber,
                'name_kanji' => $bowler?->name_kanji ?: $name,
                'title_name' => $titleName,
                'title_category' => $category,
                'year' => $year,
                'won_date' => $wonDate,
                'venue_name' => $venueName,
                'source_url' => $url,
                'source_result_url' => null,
                'source_label' => $this->sourceLabel($titleName, $venueName),
                'raw_text' => trim($line.' '.$winnerText),
                'confidence' => $bowler ? 90 : 45,
                'status' => 'candidate',
                'error' => $bowler ? null : 'pro_bowler not resolved',
            ];
            $payload['candidate_hash'] = $this->candidateHash($payload);

            $identity = $this->candidateIdentity($payload);
            if (! isset($candidates[$identity])
                || mb_strlen((string) $payload['venue_name']) > mb_strlen((string) $candidates[$identity]['venue_name'])) {
                $candidates[$identity] = $payload;
            }
        }

        return array_values($candidates);
    }

    /**
     * @param  array<int,string>  $lines
     * @return array<int,array<string,mixed>>
     */
    private function parseOfficialTournamentCandidates(array $lines, string $url, string $titleName, ?int $year): array
    {
        $wonDate = $this->extractTournamentEndDate($lines, $year);
        $candidates = [];

        foreach ($lines as $index => $line) {
            $winnerSegments = $this->officialWinnerSegments($line);
            if ($winnerSegments === []) {
                continue;
            }

            foreach ($winnerSegments as $winnerSegment) {
                $name = $this->officialWinnerName($winnerSegment);
                $winnerText = $this->officialWinnerText($lines, $index, $winnerSegment, $name);
                $licenseNumber = $this->licenseNumber($winnerText);
                if ($licenseNumber === null) {
                    continue;
                }

                if ($name === null) {
                    $name = $this->winnerName($winnerText);
                }
                if ($name === null || $this->looksLikeInvalidWinnerName($name)) {
                    continue;
                }

                $bowler = $this->resolveBowler($licenseNumber, $name, $lines)
                    ?? $this->resolveBowlerFromWinnerText($licenseNumber, $winnerText, $lines);
                $division = $this->officialWinnerDivision($winnerSegment);
                $payload = [
                    'pro_bowler_id' => $bowler?->id,
                    'license_no' => $bowler?->license_no,
                    'license_no_num' => $bowler?->license_no_num ?: $licenseNumber,
                    'name_kanji' => $bowler?->name_kanji ?: $name,
                    'title_name' => $titleName,
                    'title_category' => 'normal',
                    'year' => $year,
                    'won_date' => $wonDate,
                    'venue_name' => $division,
                    'source_url' => $url,
                    'source_result_url' => null,
                    'source_label' => $this->sourceLabel($titleName, $division),
                    'raw_text' => $winnerText,
                    'confidence' => $bowler ? 90 : 45,
                    'status' => 'candidate',
                    'error' => $bowler ? null : 'pro_bowler not resolved',
                ];
                $payload['candidate_hash'] = $this->candidateHash($payload);

                $identity = $this->candidateIdentity($payload);
                if (! isset($candidates[$identity])
                    || ((int) ($payload['pro_bowler_id'] ?? 0) > 0
                        && (int) ($candidates[$identity]['pro_bowler_id'] ?? 0) === 0)) {
                    $candidates[$identity] = $payload;
                }
            }
        }

        return array_values($candidates);
    }

    private function decodeHtml(string $html): string
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'EUC-JP', 'ISO-2022-JP'], true) ?: 'UTF-8';

        return mb_convert_encoding($html, 'UTF-8', $encoding);
    }

    /**
     * @return array<int,string>
     */
    private function htmlToLines(string $html): array
    {
        $html = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $html) ?: $html;
        $html = preg_replace('/<\s*\/?(?:p|div|tr|td|th|li|h[1-6])[^>]*>/iu', "\n", $html) ?: $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\t", "\xc2\xa0"], "\n", $text);

        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = $this->cleanText($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function extractTitleName(string $html, array $lines): string
    {
        $titleKeywords = [
            'シーズントライアル', '選手権', 'オープン', 'カップ', 'トーナメント',
            '新人戦', '記念大会', 'ドリームマッチ', 'マッチ', 'マスターズ', 'ROUND1', 'FINAL',
        ];

        foreach (array_slice($lines, 8, 55) as $line) {
            if ($this->looksLikeTournamentTitle($line, $titleKeywords)
                && ! $this->looksLikeNonTitleLine($line)
                && ! $this->looksLikeNavigationLine($line)) {
                return $line;
            }
        }

        foreach (array_slice($lines, 0, 120) as $line) {
            if (preg_match('/(?:20\d{2}|19\d{2})/u', $line) === 1
                && $this->looksLikeTournamentTitle($line, $titleKeywords)
                && ! $this->looksLikeNonTitleLine($line)) {
                return $line;
            }
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (['h2', 'h1', 'title'] as $tagName) {
            foreach ($dom->getElementsByTagName($tagName) as $node) {
                $text = $this->cleanText($node->textContent);
                if ($text !== '' && $this->looksLikeTournamentTitle($text, $titleKeywords)) {
                    return $text;
                }
            }
        }

        foreach ($lines as $line) {
            if ($this->looksLikeTournamentTitle($line, $titleKeywords)) {
                return $line;
            }
        }

        return 'JPBA公式サイト確認タイトル';
    }

    /**
     * @param  array<int,string>  $keywords
     */
    private function looksLikeTournamentTitle(string $text, array $keywords): bool
    {
        if (in_array($this->cleanText($text), ['トーナメント', 'プロボウリングトーナメント'], true)) {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeNonTitleLine(string $text): bool
    {
        foreach ([
            '出場', '資格', '資料', '開催要項', '日程', '成績', '大会記録', '大会取材',
            '優勝者シード', 'トーナメントシード', '会場案内', '開催案内',
        ] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeNavigationLine(string $text): bool
    {
        $hits = 0;
        foreach (['JPBAについて', 'スケジュール', '選手データ', 'インストラクター', 'トピックス'] as $label) {
            if (str_contains($text, $label)) {
                $hits++;
            }
        }

        return $hits >= 2 || in_array($text, ['公認トーナメント', '承認イベント'], true);
    }

    private function looksLikeSeasonTrialLink(string $haystack, string $text): bool
    {
        return str_contains($haystack, '/st_')
            || str_contains($haystack, '/st1')
            || str_contains($haystack, '/st2')
            || str_contains($haystack, '/st3')
            || str_contains($haystack, '/st4')
            || str_contains($haystack, 'stspring')
            || str_contains($haystack, 'stsummer')
            || str_contains($haystack, 'stautumn')
            || str_contains($haystack, 'stwinter')
            || str_contains($text, 'シーズントライアル');
    }

    private function looksLikeOfficialTournamentPageUrl(string $url): bool
    {
        return preg_match('#/information/tournament/tournament20\d{2}/.+\.html(?:[?\#].*)?$#iu', $url) === 1;
    }

    private function looksLikeNonTitleTournamentLink(string $url, string $text): bool
    {
        $haystack = strtolower($url.' '.$text);

        return str_contains($haystack, 'ranking')
            || str_contains($haystack, 'prioritypositions')
            || str_contains($haystack, 'jpba_qualify')
            || preg_match('#/jpba_final\.html(?:[?\#].*)?$#iu', $url) === 1
            || preg_match('#/wtr/#iu', $url) === 1
            || $this->isExplicitNonTitleTournament($url, $text);
    }

    private function isExplicitNonTitleTournament(string $url, string $titleName): bool
    {
        $normalized = preg_replace('/[\s　]+/u', '', $this->cleanText($titleName)) ?: $titleName;

        return preg_match(
            '#/tournament2018/01round1/final/round1gcb2018_final\.html(?:[?\#].*)?$#iu',
            $url
        ) === 1
            || (str_contains($normalized, 'ROUND1GRANDCHAMPIONSHIPBOWLING2018')
                && str_contains($normalized, '三団体グランドチャンピオン大会'));
    }

    private function resolveUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        if (str_starts_with($href, '/')) {
            return rtrim(self::BASE_URL, '/').$href;
        }

        return preg_replace('/[^\/]+$/', '', $baseUrl).$href;
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function extractYear(string $titleName, array $lines): ?int
    {
        $source = $titleName.' '.implode(' ', array_slice($lines, 0, 120));
        if (preg_match('/(20\d{2}|19\d{2})\s*年?/u', $source, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function extractYearFromUrl(string $url): ?int
    {
        if (preg_match('#/tournament(20\d{2}|19\d{2})/#iu', $url, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function titleCategory(string $titleName, array $lines): string
    {
        return str_contains($titleName, 'シーズントライアル') ? 'season_trial' : 'normal';
    }

    /**
     * @param  array<int,string>  $lines
     * @return array<string,string>
     */
    private function extractVenueDates(array $lines, ?int $year): array
    {
        if (! $year) {
            return [];
        }

        $dates = [];
        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/(\d{1,2})\/(\d{1,2})/u', $lines[$i], $m) !== 1) {
                continue;
            }

            $date = sprintf('%04d-%02d-%02d', $year, (int) $m[1], (int) $m[2]);
            if (preg_match_all('/([A-ZＡ-Ｚ])\s*会場/u', $lines[$i], $venues) >= 1) {
                foreach ($venues[1] as $venueLetter) {
                    $key = $this->normalizeVenueKey($venueLetter.'会場');
                    if (! isset($dates[$key])) {
                        $dates[$key] = $date;
                    }
                }

                continue;
            }

            for ($j = $i + 1; $j <= min($i + 30, count($lines) - 1); $j++) {
                if (preg_match('/\d{1,2}\/\d{1,2}/u', $lines[$j]) === 1) {
                    break;
                }

                if (preg_match_all('/([A-ZＡ-Ｚ])\s*会場/u', $lines[$j], $venues) >= 1) {
                    foreach ($venues[1] as $venueLetter) {
                        $key = $this->normalizeVenueKey($venueLetter.'会場');
                        if (! isset($dates[$key])) {
                            $dates[$key] = $date;
                        }
                    }
                }
            }
        }

        return $dates;
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function extractTournamentEndDate(array $lines, ?int $fallbackYear): ?string
    {
        foreach (array_slice($lines, 0, 140) as $line) {
            $line = mb_convert_kana($line, 'n', 'UTF-8');
            if (! str_contains($line, '日')) {
                continue;
            }

            if (preg_match(
                '/(20\d{2}|19\d{2})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日?.*?[～〜~\-]\s*(?:(\d{1,2})\s*月\s*)?(\d{1,2})\s*日/u',
                $line,
                $matches
            ) === 1) {
                $year = (int) $matches[1];
                $startMonth = (int) $matches[2];
                $startDay = (int) $matches[3];
                $endMonth = ($matches[4] ?? '') !== '' ? (int) $matches[4] : $startMonth;
                $endDay = (int) $matches[5];

                if (($matches[4] ?? '') === '' && $endDay < $startDay) {
                    $endMonth++;
                    if ($endMonth === 13) {
                        $endMonth = 1;
                        $year++;
                    }
                }

                return $this->validDate($year, $endMonth, $endDay);
            }

            if (preg_match('/(20\d{2}|19\d{2})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日/u', $line, $matches) === 1) {
                return $this->validDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
            }

            if ($fallbackYear
                && preg_match('/(\d{1,2})\s*月\s*(\d{1,2})\s*日?.*?[～〜~\-]\s*(?:(\d{1,2})\s*月\s*)?(\d{1,2})\s*日/u', $line, $matches) === 1) {
                $year = $fallbackYear;
                $startMonth = (int) $matches[1];
                $startDay = (int) $matches[2];
                $endMonth = ($matches[3] ?? '') !== '' ? (int) $matches[3] : $startMonth;
                $endDay = (int) $matches[4];
                if (($matches[3] ?? '') === '' && $endDay < $startDay) {
                    $endMonth++;
                    if ($endMonth === 13) {
                        $endMonth = 1;
                        $year++;
                    }
                }

                return $this->validDate($year, $endMonth, $endDay);
            }
        }

        return null;
    }

    private function validDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function looksLikeOfficialWinnerLine(string $line): bool
    {
        $line = $this->cleanText($line);
        $line = preg_replace('/優\s+勝/u', '優勝', $line) ?: $line;

        foreach ([
            '優勝賞金', '優勝副賞', '優勝ボール', '優勝者シード', '歴代優勝',
            '優勝決定戦', '再優勝決定戦', 'アマチュアの部優勝', 'ベストアマ', 'ジュニア',
        ] as $excluded) {
            if (str_contains($line, $excluded)) {
                return false;
            }
        }

        $unwrapped = preg_replace('/^[\s【\[［＜<（(]+/u', '', $line) ?: $line;

        return preg_match(
            '/^(?:(?:男子|女子|男子プロ|女子プロ|プロ男子|プロ女子|男子の部|女子の部|プロの部|マスターズ|クイーンズ)\s*)?優勝(?:者)?(?!賞金|副賞|ボール|シード|決定戦|への|回数)/u',
            $unwrapped
        ) === 1;
    }

    /**
     * @return array<int,string>
     */
    private function officialWinnerSegments(string $line): array
    {
        $line = preg_replace('/優\s+勝/u', '優勝', $this->cleanText($line)) ?: $line;
        preg_match_all(
            '/(?:(?:男子|女子|男子プロ|女子プロ|プロ男子|プロ女子|男子の部|女子の部|プロの部|マスターズ|クイーンズ)\s*)?優勝(?:者)?(?!賞金|副賞|ボール|シード|決定戦|への|回数)/u',
            $line,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $markers = $matches[0] ?? [];
        $segments = [];
        foreach ($markers as $index => $marker) {
            $offset = (int) $marker[1];
            $prefix = mb_substr(substr($line, 0, $offset), -18);
            if (preg_match('/(?:アマチュア|ベストアマ|ジュニア)[^優勝]{0,10}$/u', $prefix) === 1) {
                continue;
            }

            $nextOffset = isset($markers[$index + 1]) ? (int) $markers[$index + 1][1] : strlen($line);
            $segment = trim(substr($line, $offset, $nextOffset - $offset), " \t\n\r\0\x0B[]<>");
            $segment = preg_replace('/^[【＜（]+|[】＞）]+$/u', '', $segment) ?: $segment;
            if ($segment !== '' && $this->looksLikeOfficialWinnerLine($segment)) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function officialWinnerText(array $lines, int $index, string $winnerSegment, ?string $winnerName): string
    {
        $parts = [$winnerSegment];
        if ($this->licenseNumber($winnerSegment) !== null) {
            return $winnerName === null || $this->licenseIsNearWinnerName($winnerSegment, $winnerName)
                ? $winnerSegment
                : '';
        }

        for ($i = $index + 1; $i <= min($index + 8, count($lines) - 1); $i++) {
            if ($this->officialWinnerSegments($lines[$i]) !== []) {
                continue;
            }

            $parts[] = $lines[$i];
            $joined = implode(' ', $parts);
            $currentHasWinnerName = $winnerName !== null
                && $this->licenseIsNearWinnerName($lines[$i], $winnerName);
            $previousIsWinnerName = $winnerName !== null
                && $i > $index + 1
                && $this->normalizeName($lines[$i - 1]) === $this->normalizeName($winnerName);
            $isBareLicenseLine = preg_match('/^[（(]?\s*\d+\s*期\s+No\.?/iu', $lines[$i]) === 1;
            if ($this->licenseNumber($lines[$i]) !== null
                && ($winnerName === null
                    || $currentHasWinnerName
                    || ($previousIsWinnerName && $isBareLicenseLine))) {
                return $joined;
            }
        }

        return $winnerName === null ? implode(' ', $parts) : $winnerSegment;
    }

    private function licenseIsNearWinnerName(string $text, string $winnerName): bool
    {
        $text = mb_convert_kana($text, 'n', 'UTF-8');
        if (preg_match('/No\.?\s*\d+/iu', $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return false;
        }

        $beforeLicense = substr($text, 0, (int) $matches[0][1]);
        $nearLicense = mb_substr($this->normalizeName($beforeLicense), -20);

        return str_contains($nearLicense, $this->normalizeName($winnerName));
    }

    private function officialWinnerName(string $line): ?string
    {
        $line = preg_replace('/優\s+勝/u', '優勝', $this->cleanText($line)) ?: $line;
        if (preg_match('/優勝(?:者)?\s*(?:[！!：:]\s*)?([^！!】\]］＞><（(]+)/u', $line, $matches) !== 1) {
            return null;
        }

        $name = preg_replace('/\s*JPBA\s*\d+\s*期.*$/iu', '', $this->cleanText($matches[1])) ?: $matches[1];
        $name = preg_replace('/\s*\d+\s*期.*$/u', '', $this->cleanText($name)) ?: $name;
        $name = preg_replace('/\s*JPBA\s*$/iu', '', $this->cleanText($name)) ?: $name;
        $name = preg_replace('/(?:選手|プロ)\s*$/u', '', $this->cleanText($name)) ?: $name;
        $name = trim($this->cleanText($name), " \t\n\r\0\x0B[]<>");
        $name = preg_replace('/^[【＜（]+|[】＞）]+$/u', '', $name) ?: $name;

        return $name !== '' ? $name : null;
    }

    private function officialWinnerDivision(string $line): string
    {
        foreach (['マスターズ', 'クイーンズ', '男子プロ', '女子プロ', '男子の部', '女子の部', 'プロの部', '男子', '女子'] as $division) {
            if (str_contains($line, $division)) {
                return $division;
            }
        }

        return '';
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function nextWinnerText(array $lines, int $index): ?string
    {
        $parts = [];
        for ($i = $index + 1; $i <= min($index + 6, count($lines) - 1); $i++) {
            $line = $lines[$i];
            if (str_contains($line, '会場') && str_contains($line, '優勝')) {
                break;
            }
            $parts[] = $line;
            $joined = implode(' ', $parts);
            if ($this->licenseNumber($joined) !== null) {
                return $joined;
            }
        }

        return null;
    }

    private function licenseNumber(string $text): ?int
    {
        $text = mb_convert_kana($text, 'n', 'UTF-8');
        if (preg_match('/No\.?\s*([0-9]{1,5})/iu', $text, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function winnerName(string $text): ?string
    {
        $text = $this->cleanText($text);
        $text = preg_replace('/^.*?優\s*勝(?:者)?\s*(?:[！!：:]\s*)?/u', '', $text) ?: $text;
        $text = preg_replace('/[（(].*$/u', '', $text) ?: $text;
        $text = preg_replace('/\s*No\.?\s*\d+.*$/iu', '', $text) ?: $text;
        $text = preg_replace('/\s*JPBA\s*\d+\s*期.*$/iu', '', $text) ?: $text;
        $text = preg_replace('/\s*\d+期.*$/u', '', $text) ?: $text;
        $text = preg_replace('/\s*JPBA\s*$/iu', '', $text) ?: $text;
        $text = $this->cleanText($text);

        return $text !== '' ? $text : null;
    }

    private function winnerNameFromVenueLine(string $text): ?string
    {
        $text = $this->cleanText($text);
        if (preg_match('/優勝\s*([^】\]]+)/u', $text, $m) !== 1) {
            return null;
        }

        $name = $this->cleanText($m[1]);

        return $name !== '' ? $name : null;
    }

    private function looksLikeInvalidWinnerName(string $name): bool
    {
        $name = $this->cleanText($name);

        return $name === ''
            || mb_strlen($name) > 40
            || preg_match('/^(?:の(?:際|時|近藤)|者の|母[・･]|父[・･]|師匠[・･])/u', $name) === 1
            || preg_match('/(?:選手|JPBA)\s*$/iu', $name) === 1
            || preg_match('/^\d+期/u', $name) === 1
            || preg_match('/(?:優勝|賞金|副賞|大会|選手権|トーナメント|No\.)/iu', $name) === 1;
    }

    /**
     * @param  array<int,string>  $pageLines
     */
    private function resolveBowler(int $licenseNumber, string $name, array $pageLines): ?ProBowler
    {
        $query = ProBowler::query()
            ->where('license_no_num', $licenseNumber)
            ->orderBy('id');

        $gender = $this->pageGender($pageLines);
        if ($gender !== null) {
            $query->where('sex', $gender);
        }

        $matches = $query->get();
        if ($matches->count() === 1) {
            return $matches->first();
        }

        $needle = $this->normalizeName($name);
        if ($matches->isEmpty()) {
            return ProBowler::query()
                ->orderBy('id')
                ->get()
                ->first(function (ProBowler $bowler) use ($needle) {
                    return $this->normalizeName((string) $bowler->name_kanji) === $needle;
                });
        }

        return $matches->first(function (ProBowler $bowler) use ($needle) {
            return $this->normalizeName((string) $bowler->name_kanji) === $needle;
        });
    }

    /**
     * @param  array<int,string>  $pageLines
     */
    private function resolveBowlerFromWinnerText(int $licenseNumber, string $winnerText, array $pageLines): ?ProBowler
    {
        $query = ProBowler::query()
            ->where('license_no_num', $licenseNumber)
            ->orderBy('id');

        $gender = $this->pageGender($pageLines);
        if ($gender !== null) {
            $query->where('sex', $gender);
        }

        $matches = $query->get()->filter(function (ProBowler $bowler) use ($winnerText) {
            return $this->licenseIsNearWinnerName($winnerText, (string) $bowler->name_kanji);
        })->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param  array<int,string>  $lines
     */
    private function pageGender(array $lines): ?int
    {
        $source = implode(' ', array_slice($lines, 0, 120));
        if (str_contains($source, '女子') && ! str_contains($source, '男子')) {
            return 2;
        }
        if (str_contains($source, '男子') && ! str_contains($source, '女子')) {
            return 1;
        }

        return null;
    }

    /**
     * @param  array<string,string>  $venueDates
     */
    private function dateForVenue(string $venueName, array $venueDates): ?string
    {
        if (preg_match('/([A-ZＡ-Ｚ])会場/u', $venueName, $m) !== 1) {
            return null;
        }

        return $venueDates[$this->normalizeVenueKey($m[1].'会場')] ?? null;
    }

    private function venueNameFromWinnerLine(string $line): string
    {
        $venueName = $this->cleanText(preg_replace('/\s*優勝.*$/u', '', $line) ?: $line);
        $venueName = preg_replace('/^[\[【\s]+/u', '', $venueName) ?: $venueName;

        return trim($venueName, " \t\n\r\0\x0B[]【】");
    }

    private function normalizeVenueKey(string $value): string
    {
        return mb_convert_kana($value, 'asKV', 'UTF-8');
    }

    private function sourceLabel(string $titleName, string $venueName): string
    {
        return $venueName === '' ? trim($titleName) : trim($titleName.' / '.$venueName);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function candidateHash(array $payload): string
    {
        return $this->candidateIdentity($payload);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function candidateIdentity(array $payload): string
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
