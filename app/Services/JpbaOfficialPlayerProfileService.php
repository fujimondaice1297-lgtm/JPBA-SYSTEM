<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class JpbaOfficialPlayerProfileService
{
    public const BASE_URL = 'https://www.jpba1.jp';

    /**
     * @return array<string,mixed>
     */
    public function fetch(string $licenseNo): array
    {
        $licenseNo = strtoupper(trim($licenseNo));
        $url = self::BASE_URL . '/player1/detail.html?id=' . rawurlencode($licenseNo);

        return $this->fetchUrl($url);
    }

    /**
     * @return array<string,mixed>
     */
    public function fetchTournamentYear(string $licenseNo, int $year): array
    {
        if ($year < 1900 || $year > 2100) {
            throw new RuntimeException('Official profile tournament year is out of range.');
        }

        $licenseNo = strtoupper(trim($licenseNo));
        $url = self::BASE_URL
            . '/player1/detail.html?id='
            . rawurlencode($licenseNo)
            . '&year='
            . $year;

        return $this->fetchUrl($url);
    }

    /**
     * @param array<int,int> $years
     * @return array{
     *   profiles:array<int,array<string,mixed>>,
     *   errors:array<int,string>
     * }
     */
    public function fetchTournamentYears(
        string $licenseNo,
        array $years,
        int $concurrency = 1,
        int $sleepMs = 100
    ): array {
        $licenseNo = strtoupper(trim($licenseNo));
        $years = array_values(array_unique(array_filter(
            array_map('intval', $years),
            fn (int $year): bool => $year >= 1900 && $year <= 2100
        )));
        $concurrency = max(1, min(8, $concurrency));
        $profiles = [];
        $errors = [];

        if ($concurrency === 1) {
            foreach ($years as $year) {
                try {
                    $profiles[$year] = $this->fetchTournamentYear($licenseNo, $year);
                } catch (Throwable $e) {
                    $errors[$year] = $e->getMessage();

                    break;
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }

            krsort($profiles);
            krsort($errors);

            return [
                'profiles' => $profiles,
                'errors' => $errors,
            ];
        }

        foreach (array_chunk($years, $concurrency) as $chunk) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($licenseNo, $chunk): void {
                    foreach ($chunk as $year) {
                        $pool->as((string) $year)
                            ->timeout(20)
                            ->withoutVerifying()
                            ->withHeaders($this->headers())
                            ->get($this->tournamentYearUrl($licenseNo, $year));
                    }
                });

                foreach ($chunk as $year) {
                    try {
                        $profiles[$year] = $this->profileFromResponse(
                            $responses[$year],
                            $this->tournamentYearUrl($licenseNo, $year)
                        );
                    } catch (Throwable) {
                        $profiles[$year] = $this->fetchTournamentYear($licenseNo, $year);
                    }
                }
            } catch (Throwable) {
                foreach ($chunk as $year) {
                    try {
                        $profiles[$year] = $this->fetchTournamentYear($licenseNo, $year);
                    } catch (Throwable $e) {
                        $errors[$year] = $e->getMessage();
                    }
                }
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        krsort($profiles);
        krsort($errors);

        return [
            'profiles' => $profiles,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchUrl(string $url): array
    {
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withoutVerifying()
            ->withHeaders($this->headers())
            ->get($url);

        return $this->profileFromResponse($response, $url);
    }

    /**
     * @return array<string,mixed>
     */
    private function profileFromResponse(Response $response, string $url): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('Official profile HTTP status ' . $response->status());
        }

        $html = (string) $response->body();
        if (! str_contains($html, 'player-detail')) {
            throw new RuntimeException('Official profile body did not contain player-detail');
        }

        return $this->parse($html, $url);
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => 'JPBA-SYSTEM forward-test profile import',
        ];
    }

    private function tournamentYearUrl(string $licenseNo, int $year): string
    {
        return self::BASE_URL
            . '/player1/detail.html?id='
            . rawurlencode($licenseNo)
            . '&year='
            . $year;
    }

    /**
     * @return array<string,mixed>
     */
    public function parse(string $html, string $url): array
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $summary = [];
        $awards = [];
        $annualRecords = [];
        $tournamentRecords = [];

        foreach ($dom->getElementsByTagName('table') as $table) {
            $rows = $this->tableRows($table);
            if ($rows === []) {
                continue;
            }

            $annualRecords = array_merge(
                $annualRecords,
                $this->annualRecords($rows)
            );

            if (count($rows) < 2) {
                continue;
            }

            $headers = $rows[0];
            $values = $rows[1];

            if ($this->hasHeaders($headers, [
                '開催年',
                '開催日',
                '大会名',
                '順位',
                '獲得賞金',
                'アベレージ',
            ])) {
                $tournamentRecords = $this->tournamentRecords($rows);
            }

            foreach ($headers as $index => $header) {
                $value = $values[$index] ?? null;
                if ($value === null) {
                    continue;
                }

                match ($header) {
                    '優勝回数' => $this->putWinCounts($summary, $value),
                    '総ゲーム数' => $this->putParsedValue($summary, 'official_total_games', $this->intValue($value)),
                    'トータルピン' => $this->putParsedValue($summary, 'official_total_pins', $this->intValue($value)),
                    '総賞金額' => $this->putParsedValue($summary, 'official_total_prize_money', $this->intValue($value)),
                    '通算アベレージ' => $this->putParsedValue($summary, 'official_career_average', $this->decimalValue($value)),
                    '公認パーフェクト' => $this->putParsedValue($awards, 'perfect_count', $this->intValue($value)),
                    '800シリーズ' => $this->putParsedValue($awards, 'eight_hundred_count', $this->intValue($value)),
                    '7-10スプリットメイド' => $this->putParsedValue($awards, 'seven_ten_count', $this->intValue($value)),
                    default => null,
                };
            }
        }

        $summary += [
            'official_win_count' => null,
            'season_trial_win_count' => 0,
            'official_total_games' => null,
            'official_total_pins' => null,
            'official_total_prize_money' => null,
            'official_career_average' => null,
        ];
        $summary['official_profile_url'] = $url;
        $summary['official_profile_imported_at'] = now();
        $summary['official_profile_import_error'] = null;

        $awards += [
            'perfect_count' => 0,
            'eight_hundred_count' => 0,
            'seven_ten_count' => 0,
        ];
        $awards['award_total_count'] = array_sum(array_map('intval', $awards));
        $annualRecords = collect($annualRecords)
            ->unique('season_key')
            ->sortByDesc('season_end_year')
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'awards' => $awards,
            'title' => $this->pageTitle($dom),
            'annual_records' => $annualRecords,
            'participation_years' => $this->participationYears($dom),
            'tournament_records' => $tournamentRecords,
        ];
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
                if (! $child instanceof \DOMElement) {
                    continue;
                }
                if (! in_array($child->tagName, ['th', 'td'], true)) {
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

    private function pageTitle(\DOMDocument $dom): ?string
    {
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length === 0) {
            return null;
        }

        return $this->cleanText($titles->item(0)?->textContent);
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,string> $expected
     */
    private function hasHeaders(array $headers, array $expected): bool
    {
        return count(array_intersect($expected, $headers)) === count($expected);
    }

    /**
     * @param array<int,array<int,string>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function annualRecords(array $rows): array
    {
        $records = [];

        foreach ($rows as $row) {
            if (count($row) < 7) {
                continue;
            }

            $season = $this->parseSeason($row[0]);
            if ($season === null) {
                continue;
            }

            $records[] = [
                ...$season,
                'ranking_rank' => $this->intValue($row[1]),
                'games' => $this->intValue($row[2]),
                'total_pin' => $this->intValue($row[3]),
                'points' => $this->decimalValue($row[4]),
                'average' => $this->decimalValue($row[5]),
                'prize_money' => $this->intValue($row[6]),
            ];
        }

        return $records;
    }

    /**
     * @return array<string,int|string>|null
     */
    private function parseSeason(string $value): ?array
    {
        $seasonKey = mb_convert_kana($this->cleanText($value), 'n', 'UTF-8');
        $seasonKey = str_replace(['年', '－', '―', '‐', '–', '—'], ['', '-', '-', '-', '-', '-'], $seasonKey);
        $seasonKey = preg_replace('/\s+/u', '', $seasonKey) ?: $seasonKey;

        if (! preg_match('/^(\d{4})(?:-(\d{2}|\d{4}))?$/', $seasonKey, $matches)) {
            return null;
        }

        $startYear = (int) $matches[1];
        $endYear = $startYear;
        if (isset($matches[2]) && $matches[2] !== '') {
            $endYear = strlen($matches[2]) === 2
                ? ((int) floor($startYear / 100) * 100) + (int) $matches[2]
                : (int) $matches[2];
        }

        return [
            'season_key' => $seasonKey,
            'season_start_year' => $startYear,
            'season_end_year' => $endYear,
        ];
    }

    /**
     * @param array<int,array<int,string>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function tournamentRecords(array $rows): array
    {
        $records = [];

        foreach (array_slice($rows, 1) as $row) {
            if (count($row) < 6) {
                continue;
            }

            $year = $this->intValue($row[0]);
            $heldOn = $year === null ? null : $this->heldOn($year, $row[1]);
            $tournamentName = $this->cleanText($row[2]);

            if ($year === null || $heldOn === null || $tournamentName === '') {
                continue;
            }

            $records[] = [
                'season_year' => $year,
                'held_on' => $heldOn,
                'tournament_name' => $tournamentName,
                'ranking_rank' => $this->intValue($row[3]),
                'prize_money' => $this->intValue($row[4]),
                'average' => $this->decimalValue($row[5]),
            ];
        }

        return $records;
    }

    private function heldOn(int $year, string $value): ?string
    {
        $value = mb_convert_kana($this->cleanText($value), 'n', 'UTF-8');
        if (! preg_match('/(\d{1,2})\s*[\/.-]\s*(\d{1,2})/', $value, $matches)) {
            return null;
        }

        $month = (int) $matches[1];
        $day = (int) $matches[2];
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->format('Y-m-d');
    }

    /**
     * @return array<int,int>
     */
    private function participationYears(\DOMDocument $dom): array
    {
        $years = [];

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES, 'UTF-8');
            if (! str_contains($href, 'detail.html') || ! str_contains($href, 'year=')) {
                continue;
            }

            if (preg_match('/[?&]year=(\d{4})(?:&|#|$)/', $href, $matches)) {
                $years[] = (int) $matches[1];
            }
        }

        rsort($years);

        return array_values(array_unique($years));
    }

    /**
     * @param array<string,mixed> $target
     */
    private function putParsedValue(array &$target, string $key, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (array_key_exists($key, $target)) {
            return;
        }

        $target[$key] = $value;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private function putWinCounts(array &$summary, string $value): void
    {
        $this->putParsedValue($summary, 'official_win_count', $this->intValue($value));

        $seasonTrialWins = $this->seasonTrialWinCountValue($value);
        if ($seasonTrialWins !== null) {
            $this->putParsedValue($summary, 'season_trial_win_count', $seasonTrialWins);
        }
    }

    private function cleanText(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        $value = str_replace(["\r\n", "\r", "\n", "\t", "\xc2\xa0"], ' ', $value);
        $value = preg_replace('/[　 ]+/u', ' ', $value) ?: $value;

        return trim($value);
    }

    private function intValue(?string $value): ?int
    {
        $value = mb_convert_kana((string) $value, 'n', 'UTF-8');
        if (! preg_match('/\d[\d,]*/u', $value, $m)) {
            return null;
        }

        return (int) str_replace(',', '', $m[0]);
    }

    private function seasonTrialWinCountValue(?string $value): ?int
    {
        $value = mb_convert_kana((string) $value, 'n', 'UTF-8');

        if (preg_match('/(?:ST|ＳＴ|シーズントライアル)\s*[^\d]*(\d[\d,]*)/iu', $value, $m)) {
            return (int) str_replace(',', '', $m[1]);
        }

        return null;
    }

    private function decimalValue(?string $value): ?string
    {
        $value = mb_convert_kana((string) $value, 'n', 'UTF-8');
        $value = str_replace(',', '', $value);
        if (! preg_match('/\d+(?:\.\d+)?/u', $value, $m)) {
            return null;
        }

        return number_format((float) $m[0], 2, '.', '');
    }
}
