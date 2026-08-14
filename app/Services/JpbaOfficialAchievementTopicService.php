<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class JpbaOfficialAchievementTopicService
{
    public const BASE_URL = 'https://www.jpba.or.jp';

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetch(string $url): array
    {
        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'JPBA-SYSTEM official achievement migration',
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Official topic HTTP status ' . $response->status());
        }

        return $this->parse((string) $response->body(), $url);
    }

    /**
     * Extract only conservative, certifiable occurrences. An occurrence must
     * have a record keyword, a JPBA certification number, and a professional
     * license number in the same dated topic entry.
     *
     * @return array<int,array<string,mixed>>
     */
    public function parse(string $html, string $url): array
    {
        $html = $this->utf8Html($html);
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/isu', '', $html) ?: $html;
        $html = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $html);
        $html = preg_replace('/<br\s*\/?>/iu', "\n", $html) ?: $html;
        $html = preg_replace(
            '/<\/(?:p|h[1-6]|div|li|tr|section|article)>/iu',
            "\n",
            $html
        ) ?: $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\xc2\xa0"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[　\t ]+/u', ' ', $text) ?: $text;
        $text = preg_replace('/\n{2,}/u', "\n", $text) ?: $text;

        $entries = preg_split(
            '/(?=(?:^|\n)\s*(?:20\d{2}|19\d{2})[\/.-]\d{1,2}[\/.-]\d{1,2}\s*(?:\n|$))/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $records = [];
        foreach ($entries as $entry) {
            $date = $this->entryDate($entry);
            if ($date === null) {
                continue;
            }

            foreach ($this->keywordMatches($entry) as $keyword) {
                $record = $this->recordAroundKeyword(
                    $entry,
                    $keyword['type'],
                    $keyword['offset'],
                    $date,
                    $url
                );
                if ($record !== null) {
                    $records[] = $record;
                }
            }

            foreach ($this->pairedCertificationRecords($entry, $date, $url) as $record) {
                $records[] = $record;
            }
        }

        return collect($records)
            ->unique(fn (array $record) => implode('|', [
                $record['record_type'],
                $record['license_number'],
                $record['certification_number_value'],
                $record['awarded_on'],
            ]))
            ->values()
            ->all();
    }

    /**
     * A single topic entry can announce two achievements by one professional,
     * for example "JPBA公認1461号＆1462号". The ordinary nearest-number parser
     * intentionally returns only one row, so expand an explicitly paired
     * official number into two independently certifiable details.
     *
     * @return array<int,array<string,mixed>>
     */
    private function pairedCertificationRecords(
        string $entry,
        string $date,
        string $url
    ): array {
        if (! preg_match_all(
            '/JPBA\s*公認(?:男子|女子)?\s*第?\s*(\d{1,4})\s*号\s*'
                . '(?:＆|&|・|、|及び|および)\s*第?\s*(\d{1,4})\s*号/u',
            $entry,
            $pairs,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            return [];
        }

        $keywords = $this->keywordMatches($entry);
        $records = [];
        foreach ($pairs as $pair) {
            $pairOffset = (int) $pair[0][1];
            $keyword = collect($keywords)
                ->sortBy(fn (array $match): int => abs($match['offset'] - $pairOffset))
                ->first();
            if (! $keyword) {
                continue;
            }

            $base = $this->recordAroundKeyword(
                $entry,
                $keyword['type'],
                $keyword['offset'],
                $date,
                $url
            );
            if ($base === null) {
                continue;
            }

            $positions = [];
            $beforePair = substr($entry, 0, $pairOffset);
            $sectionStart = strrpos($beforePair, '「');
            if ($sectionStart === false) {
                $sectionStart = max(0, $pairOffset - 1200);
            }
            $positionSource = substr(
                $entry,
                $sectionStart,
                $pairOffset - $sectionStart + strlen((string) $pair[0][0])
            );
            if (preg_match_all(
                '/((?:予選|準決勝|決勝|ラウンドロビン|シュートアウト|ファイナル)'
                    . '[^。\n]{0,80}?\d+\s*G目|\d+\s*G目)/u',
                $positionSource,
                $positionMatches
            )) {
                $positions = array_values(array_unique(array_map(
                    fn (string $value): string => trim($value),
                    $positionMatches[1] ?? []
                )));
            }

            foreach ([(int) $pair[1][0], (int) $pair[2][0]] as $index => $number) {
                $record = $base;
                $record['certification_number_value'] = $number;
                $record['certification_number'] = '第' . $number . '号';
                if (isset($positions[$index])) {
                    $record['game_numbers'] = $positions[$index];
                }
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return array<int,array{type:string,offset:int}>
     */
    private function keywordMatches(string $entry): array
    {
        $patterns = [
            'seven_ten' => '/(?:公認\s*)?7[\-－ー–]10(?:スプリット)?メイド/iu',
            'perfect' => '/(?:公認\s*)?パーフェクト(?:ゲーム)?/u',
            'eight_hundred' => '/(?:公認\s*)?800シリーズ/u',
        ];
        $matches = [];

        foreach ($patterns as $type => $pattern) {
            preg_match_all($pattern, $entry, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] ?? [] as $match) {
                $matches[] = ['type' => $type, 'offset' => (int) $match[1]];
            }
        }

        usort($matches, fn (array $a, array $b) => $a['offset'] <=> $b['offset']);

        return $matches;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function recordAroundKeyword(
        string $entry,
        string $type,
        int $keywordOffset,
        string $date,
        string $url
    ): ?array {
        [$section, $sectionStart] = $this->topicSectionAround(
            $entry,
            $keywordOffset
        );
        $sectionKeywordOffset = $keywordOffset - $sectionStart;
        [$sentence, $sentenceStart] = $this->sentenceAround(
            $section,
            $sectionKeywordOffset
        );
        $sentenceKeywordOffset = $sectionKeywordOffset - $sentenceStart;

        $certificationNumber = $this->nearestNumber(
            $sentence,
            '/(?:JPBA\s*)?公認(?:\s*(?:男子|女子))?\s*第?\s*(\d{1,4})\s*号/iu',
            $sentenceKeywordOffset
        );
        if ($certificationNumber === null) {
            $certificationNumber = $this->nearestNumber(
                $sentence,
                '/(?<!大会)第\s*(\d{1,4})\s*号/u',
                $sentenceKeywordOffset
            );
        }
        $entryFallback = false;
        if ($certificationNumber === null) {
            $certificationNumbers = array_values(array_unique(array_merge(
                $this->allNumbers(
                    $section,
                    '/(?:JPBA\s*)?公認(?:\s*(?:男子|女子))?\s*第?\s*(\d{1,4})\s*号/iu'
                ),
                $this->allNumbers($section, '/(?<!大会)第\s*(\d{1,4})\s*号/u')
            )));
            if (count($certificationNumbers) !== 1) {
                return null;
            }
            $certificationNumber = $certificationNumbers[0];
            $entryFallback = true;
        }

        $licenseNumber = $this->nearestNumber(
            $sentence,
            '/(?:ライセンス\s*)?No[.．]?\s*(\d{1,4})/iu',
            $sentenceKeywordOffset
        );

        $windowStart = max(0, $sectionKeywordOffset - 900);
        $window = substr($section, $windowStart, 1800);
        $relativeKeywordOffset = $sectionKeywordOffset - $windowStart;
        if ($licenseNumber === null) {
            if ($entryFallback) {
                $licenseNumbers = array_values(array_unique($this->allNumbers(
                    $section,
                    '/(?:ライセンス\s*)?No[.．]?\s*(\d{1,4})/iu'
                )));
                $licenseNumber = count($licenseNumbers) === 1
                    ? $licenseNumbers[0]
                    : null;
            } else {
                $licenseNumber = $this->nextNumber(
                    $window,
                    '/(?:ライセンス\s*)?No[.．]?\s*(\d{1,4})/iu',
                    $relativeKeywordOffset
                );
            }
        }

        if ($licenseNumber === null) {
            return null;
        }

        $tournamentName = null;
        $quotedNames = [];
        if (preg_match_all('/「([^」]{3,255})」/u', $section, $quotes, PREG_OFFSET_CAPTURE)) {
            $quotedNames = $quotes[1] ?? [];
        } elseif (preg_match_all('/『([^』]{3,255})』/u', $section, $quotes, PREG_OFFSET_CAPTURE)) {
            $quotedNames = $quotes[1] ?? [];
        }
        if ($quotedNames !== []) {
            $nearestQuote = $this->nearestMatch($quotedNames, $sectionKeywordOffset);
            $tournamentName = $nearestQuote[0] ?? null;
        }
        if (
            $tournamentName !== null
            && ! $this->looksLikeTournamentName($tournamentName)
        ) {
            $tournamentName = null;
        }
        if ($tournamentName === null) {
            $entryQuotes = [];
            if (preg_match_all(
                '/「([^」]{3,255})」/u',
                $entry,
                $quotes,
                PREG_OFFSET_CAPTURE
            )) {
                $entryQuotes = $quotes[1] ?? [];
            } elseif (preg_match_all(
                '/『([^』]{3,255})』/u',
                $entry,
                $quotes,
                PREG_OFFSET_CAPTURE
            )) {
                $entryQuotes = $quotes[1] ?? [];
            }
            $entryQuotes = array_values(array_filter(
                $entryQuotes,
                fn (array $quote): bool => $this->looksLikeTournamentName($quote[0])
            ));
            if ($entryQuotes !== []) {
                $nearestQuote = $this->nearestMatch($entryQuotes, $keywordOffset);
                $tournamentName = $nearestQuote[0] ?? null;
            }
        }

        $evidence = $this->evidenceExcerpt($section, $sectionKeywordOffset);
        $gameText = $this->positionText($section, $sectionKeywordOffset);
        $frameText = $type === 'seven_ten'
            ? $this->frameText($section, $sectionKeywordOffset)
            : null;
        $seriesText = $type === 'eight_hundred'
            ? $this->seriesText($section, $sectionKeywordOffset)
            : null;

        return [
            'record_type' => $type,
            'license_number' => $licenseNumber,
            'certification_number_value' => $certificationNumber,
            'certification_number' => '第' . $certificationNumber . '号',
            'awarded_on' => $date,
            'tournament_name' => $tournamentName ?: '大会名は公式記事本文を参照',
            'game_numbers' => $gameText,
            'frame_number' => $frameText,
            'series_label' => $seriesText,
            'source_url' => $url,
            'source_label' => 'JPBA公式トピックス',
            'evidence_text' => $evidence,
        ];
    }

    /**
     * @param array<int,array{0:string,1:int}> $matches
     * @return array{0:string,1:int}|null
     */
    private function nearestMatch(array $matches, int $offset): ?array
    {
        $nearest = null;
        $nearestDistance = PHP_INT_MAX;
        foreach ($matches as $match) {
            $distance = abs((int) $match[1] - $offset);
            if ($distance < $nearestDistance) {
                $nearest = $match;
                $nearestDistance = $distance;
            }
        }

        return $nearest;
    }

    private function nearestNumber(string $text, string $pattern, int $offset): ?int
    {
        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $nearest = $this->nearestMatch($matches[1] ?? [], $offset);

        return $nearest ? (int) $nearest[0] : null;
    }

    private function nextNumber(string $text, string $pattern, int $offset): ?int
    {
        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        foreach ($matches[1] ?? [] as $match) {
            if ((int) $match[1] >= $offset) {
                return (int) $match[0];
            }
        }

        return null;
    }

    /**
     * @return array<int,int>
     */
    private function allNumbers(string $text, string $pattern): array
    {
        if (! preg_match_all($pattern, $text, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1] ?? []);
    }

    /**
     * A monthly date entry often contains several achievement articles. Keep
     * positions, license numbers and certification numbers inside the closest
     * "達成！" heading so adjacent achievements cannot contaminate each other.
     *
     * @return array{0:string,1:int}
     */
    private function topicSectionAround(string $entry, int $keywordOffset): array
    {
        if (! preg_match_all(
            '/(?:^|\n)\s*[^\n]{0,180}'
                . '(?:パーフェクト|800シリーズ|7[\-－ー‐–—〜～・\s]?10)'
                . '[^\n]{0,100}達成[！!]\s*(?:\n|$)/u',
            $entry,
            $headings,
            PREG_OFFSET_CAPTURE
        )) {
            return [$entry, 0];
        }

        $starts = array_values(array_unique(array_map(
            fn (array $match): int => (int) $match[1],
            $headings[0] ?? []
        )));
        sort($starts);

        $start = 0;
        $end = strlen($entry);
        foreach ($starts as $headingStart) {
            if ($headingStart <= $keywordOffset) {
                $start = $headingStart;
                continue;
            }
            $end = $headingStart;
            break;
        }

        return [substr($entry, $start, $end - $start), $start];
    }

    private function looksLikeTournamentName(string $value): bool
    {
        return preg_match(
            '/(?:大会|選手権|オープン|トーナメント|カップ|杯|新人戦|'
                . 'シーズントライアル|プロアマ|JPBA|ROUND1|CUP|OPEN|'
                . 'TOURNAMENT|CHAMPIONSHIP|CLASSIC)/iu',
            $value
        ) === 1;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function sentenceAround(string $entry, int $keywordOffset): array
    {
        $before = substr($entry, 0, $keywordOffset);
        $start = 0;
        $newline = strrpos($before, "\n");
        if ($newline !== false) {
            $start = $newline + 1;
        }
        $period = strrpos($before, '。');
        if ($period !== false) {
            $start = max($start, $period + strlen('。'));
        }

        $ends = [];
        $newline = strpos($entry, "\n", $keywordOffset);
        if ($newline !== false) {
            $ends[] = $newline + 1;
        }
        $period = strpos($entry, '。', $keywordOffset);
        if ($period !== false) {
            $ends[] = $period + strlen('。');
        }
        $end = $ends === [] ? strlen($entry) : min($ends);

        return [substr($entry, $start, $end - $start), $start];
    }

    private function entryDate(string $entry): ?string
    {
        if (! preg_match('/(?:^|\n)\s*((?:20|19)\d{2})[\/.-](\d{1,2})[\/.-](\d{1,2})/u', $entry, $match)) {
            return null;
        }

        if (! checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $match[1], $match[2], $match[3]);
    }

    private function evidenceExcerpt(string $entry, int $keywordOffset): string
    {
        $start = max(0, $keywordOffset - 500);
        $excerpt = substr($entry, $start, 1200);
        $excerpt = preg_replace('/\s+/u', ' ', $excerpt) ?: $excerpt;

        return mb_strimwidth(trim($excerpt), 0, 1800, '…', 'UTF-8');
    }

    private function positionText(string $text, int $offset): ?string
    {
        $patterns = [
            '/((?:予選|準決勝|決勝|ラウンドロビン|シュートアウト|ファイナル)[^。]{0,80}?\d+\s*G目)/u',
            '/((?:第\d+\s*シフト[^。]{0,50}?)?\d+\s*G目)/u',
            '/(決勝トーナメント\d+回戦)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                $nearest = $this->nearestMatch($matches[1] ?? [], $offset);
                if ($nearest) {
                    return preg_replace('/\s+/u', ' ', trim($nearest[0]))
                        ?: trim($nearest[0]);
                }
            }
        }

        return null;
    }

    private function frameText(string $text, int $offset): ?string
    {
        if (preg_match_all(
            '/(\d+\s*(?:フレーム|フレ|F)(?:\s*\d投目)?)/iu',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            $nearest = $this->nearestMatch($matches[1] ?? [], $offset);
            if ($nearest) {
                return trim($nearest[0]);
            }
        }

        return null;
    }

    private function seriesText(string $text, int $offset): ?string
    {
        $patterns = [
            '/((?:予選|準決勝|決勝|男子|女子|シニア|ラウンドロビン)[^。]{0,100}?(?:第\d+\s*シリーズ|前半\s*3G|後半\s*3G|3G\s*シリーズ))/u',
            '/((?:第\d+\s*シリーズ|前半\s*3G|後半\s*3G|3G\s*シリーズ))/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                $nearest = $this->nearestMatch($matches[1] ?? [], $offset);
                if ($nearest) {
                    return trim($nearest[0]);
                }
            }
        }

        return null;
    }

    private function utf8Html(string $html): string
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'EUC-JP'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            return mb_convert_encoding($html, 'UTF-8', $encoding);
        }

        return $html;
    }
}
