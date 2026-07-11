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
            throw new RuntimeException('Official tournament HTTP status ' . $response->status());
        }

        return $this->parseCandidates((string) $response->body(), $url);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function parseCandidates(string $html, string $url): array
    {
        $html = $this->decodeHtml($html);
        $lines = $this->htmlToLines($html);
        $titleName = $this->extractTitleName($html, $lines);
        $year = $this->extractYear($titleName, $lines);
        $category = $this->titleCategory($titleName, $lines);
        $venueDates = $this->extractVenueDates($lines, $year);

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
            if ($licenseNumber === null || $name === null) {
                continue;
            }

            $bowler = $this->resolveBowler($licenseNumber, $name, $lines);
            $venueName = $this->cleanText(preg_replace('/\s*優勝.*$/u', '', $line) ?: $line);
            $wonDate = $this->dateForVenue($venueName, $venueDates);

            $payload = [
                'pro_bowler_id' => $bowler?->id,
                'license_no' => $bowler?->license_no,
                'license_no_num' => $licenseNumber,
                'name_kanji' => $bowler?->name_kanji ?: $name,
                'title_name' => $titleName,
                'title_category' => $category,
                'year' => $year,
                'won_date' => $wonDate,
                'venue_name' => $venueName,
                'source_url' => $url,
                'source_result_url' => null,
                'source_label' => $this->sourceLabel($titleName, $venueName),
                'raw_text' => trim($line . ' ' . $winnerText),
                'confidence' => $bowler ? 90 : 45,
                'status' => 'candidate',
                'error' => $bowler ? null : 'pro_bowler not resolved',
            ];
            $payload['candidate_hash'] = $this->candidateHash($payload);

            $candidates[] = $payload;
        }

        return $candidates;
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
     * @param array<int,string> $lines
     */
    private function extractTitleName(string $html, array $lines): string
    {
        $titleKeywords = ['シーズントライアル', '選手権', 'オープン', 'カップ', 'トーナメント', 'ROUND1'];

        foreach (array_slice($lines, 0, 120) as $line) {
            if (preg_match('/(?:20\d{2}|19\d{2})/u', $line) === 1
                && $this->looksLikeTournamentTitle($line, $titleKeywords)
                && ! $this->looksLikeNonTitleLine($line)) {
                return $line;
            }
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
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
     * @param array<int,string> $keywords
     */
    private function looksLikeTournamentTitle(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeNonTitleLine(string $text): bool
    {
        foreach (['出場', '資格', '資料', '開催要項', '日程', '成績', '大会記録', '大会取材'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,string> $lines
     */
    private function extractYear(string $titleName, array $lines): ?int
    {
        $source = $titleName . ' ' . implode(' ', array_slice($lines, 0, 120));
        if (preg_match('/(20\d{2}|19\d{2})\s*年?/u', $source, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param array<int,string> $lines
     */
    private function titleCategory(string $titleName, array $lines): string
    {
        $source = $titleName . ' ' . implode(' ', array_slice($lines, 0, 80));

        return str_contains($source, 'シーズントライアル') ? 'season_trial' : 'normal';
    }

    /**
     * @param array<int,string> $lines
     * @return array<string,string>
     */
    private function extractVenueDates(array $lines, ?int $year): array
    {
        if (! $year) {
            return [];
        }

        $dates = [];
        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/^(\d{1,2})\/(\d{1,2})$/u', $lines[$i], $m) !== 1) {
                continue;
            }

            $date = sprintf('%04d-%02d-%02d', $year, (int) $m[1], (int) $m[2]);
            for ($j = $i + 1; $j <= min($i + 4, count($lines) - 1); $j++) {
                if (preg_match('/^([A-ZＡ-Ｚ])会場/u', $lines[$j], $venue) === 1) {
                    $dates[$this->normalizeVenueKey($venue[1] . '会場')] = $date;
                    break;
                }
            }
        }

        return $dates;
    }

    /**
     * @param array<int,string> $lines
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
        $text = preg_replace('/[（(].*$/u', '', $text) ?: $text;
        $text = preg_replace('/\s*No\.?\s*\d+.*$/iu', '', $text) ?: $text;
        $text = $this->cleanText($text);

        return $text !== '' ? $text : null;
    }

    /**
     * @param array<int,string> $pageLines
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
        return $matches->first(function (ProBowler $bowler) use ($needle) {
            return $this->normalizeName((string) $bowler->name_kanji) === $needle;
        });
    }

    /**
     * @param array<int,string> $lines
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
     * @param array<string,string> $venueDates
     */
    private function dateForVenue(string $venueName, array $venueDates): ?string
    {
        if (preg_match('/([A-ZＡ-Ｚ])会場/u', $venueName, $m) !== 1) {
            return null;
        }

        return $venueDates[$this->normalizeVenueKey($m[1] . '会場')] ?? null;
    }

    private function normalizeVenueKey(string $value): string
    {
        return mb_convert_kana($value, 'asKV', 'UTF-8');
    }

    private function sourceLabel(string $titleName, string $venueName): string
    {
        return trim($titleName . ' / ' . $venueName);
    }

    /**
     * @param array<string,mixed> $payload
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
            $payload['venue_name'] ?? '',
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
