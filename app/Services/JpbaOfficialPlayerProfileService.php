<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

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

        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'JPBA-SYSTEM forward-test profile import',
            ])
            ->get($url);

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

        foreach ($dom->getElementsByTagName('table') as $table) {
            $rows = $this->tableRows($table);
            if (count($rows) < 2) {
                continue;
            }

            $headers = $rows[0];
            $values = $rows[1];

            foreach ($headers as $index => $header) {
                $value = $values[$index] ?? null;
                if ($value === null) {
                    continue;
                }

                match ($header) {
                    '優勝回数' => $this->putParsedValue($summary, 'official_win_count', $this->intValue($value)),
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

        return [
            'summary' => $summary,
            'awards' => $awards,
            'title' => $this->pageTitle($dom),
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

    private function decimalValue(?string $value): ?string
    {
        $value = mb_convert_kana((string) $value, 'n', 'UTF-8');
        if (! preg_match('/\d+(?:\.\d+)?/u', $value, $m)) {
            return null;
        }

        return number_format((float) $m[0], 2, '.', '');
    }
}
