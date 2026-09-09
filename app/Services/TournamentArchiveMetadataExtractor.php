<?php

namespace App\Services;

class TournamentArchiveMetadataExtractor
{
    public function venue(?string $bodyHtml): ?string
    {
        $lines = $this->lines($bodyHtml);
        $venueLabelIndex = $lines->search(function (string $line): bool {
            $compact = preg_replace('/[\s　]+/u', '', $line) ?? $line;

            return $compact === '会場';
        });

        if ($venueLabelIndex === false) {
            return $this->labeledValue($bodyHtml, ['会場']);
        }

        foreach ($lines->slice($venueLabelIndex + 1, 16) as $line) {
            $venue = $this->venueFromLine($line);
            if ($venue !== null) {
                return $venue;
            }
        }

        return null;
    }

    public function organizer(?string $bodyHtml): ?string
    {
        return $this->labeledValue($bodyHtml, ['主催', '共催']);
    }

    /**
     * @param  array<int,string>  $labels
     */
    private function labeledValue(?string $bodyHtml, array $labels): ?string
    {
        $lines = $this->lines($bodyHtml);

        foreach ($lines as $index => $line) {
            $compact = preg_replace('/[\s　]+/u', '', $line) ?? $line;
            foreach ($labels as $label) {
                if ($compact === $label) {
                    return $this->cleanValue($lines->get($index + 1));
                }

                if (str_starts_with($compact, $label)) {
                    $value = preg_replace('/^'.preg_quote($label, '/').'[：:]?/u', '', $compact) ?? '';
                    if ($value !== '') {
                        return $this->cleanValue($value);
                    }
                }
            }
        }

        return null;
    }

    /** @return \Illuminate\Support\Collection<int,string> */
    private function lines(?string $bodyHtml): \Illuminate\Support\Collection
    {
        if (! is_string($bodyHtml) || trim($bodyHtml) === '') {
            return collect();
        }

        $text = preg_replace('/<\/(?:p|div|tr|li|h[1-6])>/iu', "\n", $bodyHtml) ?? $bodyHtml;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/[\s　]+/u', ' ', $line) ?? $line))
            ->filter()
            ->values();
    }

    private function venueFromLine(string $line): ?string
    {
        $line = trim($line);
        if ($line === '' || preg_match('/^(?:〒|TEL|FAX|主催|共催|主管|公認|後援|協賛)/iu', $line)) {
            return null;
        }

        if (preg_match('/^[＜\[【■].*(?:選抜|予選|本大会|準決勝|決勝|会場).*[＞\]】]?$/u', $line)) {
            return null;
        }

        $line = preg_replace('/^\d{1,2}\/\d{1,2}(?:\([^)]*\)|（[^）]*）)?\s*/u', '', $line) ?? $line;
        $line = preg_replace('/^(?:第?\d+|[A-Z])会場(?:\([^)]*\)|（[^）]*）)?\s*[：:]\s*/iu', '', $line) ?? $line;

        if (preg_match('/^(?:第?\d+|[A-Z])会場\s*[：:]?$/iu', $line)) {
            return null;
        }

        if (preg_match('/^(?:第?\d+|[A-Z])会場[（(](.+)[）)]$/iu', $line, $matches)) {
            $line = trim($matches[1]);
        }

        if (! preg_match('/(?:ボウル|ボーリング|ボウリング|レーン|スタジアム|BOWL)/iu', $line)) {
            return null;
        }

        return $this->cleanValue($line);
    }

    private function cleanValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 255);
    }
}
