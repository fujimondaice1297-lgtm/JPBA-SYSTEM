<?php

namespace App\Services;

class ProBowlerProfileNormalizer
{
    private const YEAR_FIELDS = [
        'a_class_year',
        'b_class_year',
        'c_class_year',
        'master_year',
        'coach_4_year',
        'coach_3_year',
        'coach_1_year',
        'kenkou_year',
        'school_license_year',
    ];

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function normalizeData(array $data): array
    {
        foreach (self::YEAR_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeYear($data[$field]);
            }
        }

        $this->normalizeAddressGroup($data, 'home_zip', 'home_address');
        $this->normalizeAddressGroup($data, 'work_zip', 'work_address');
        $this->normalizeAddressGroup($data, 'organization_zip', 'organization_addr1', 'organization_addr2');
        $this->normalizeAddressGroup($data, 'public_zip', 'public_addr1', 'public_addr2');
        $this->normalizeAddressGroup($data, 'mailing_zip', 'mailing_addr1', 'mailing_addr2');

        return $data;
    }

    public function normalizeYear(mixed $value): ?string
    {
        $s = $this->clean($value);
        if ($s === null) {
            return null;
        }

        $s = mb_convert_kana($s, 'n', 'UTF-8');
        $s = preg_replace('/\s+/u', '', $s) ?: $s;

        if (preg_match('/(19|20)\d{2}/', $s, $m)) {
            return $m[0];
        }

        if (preg_match('/令和(\d{1,2})年?/u', $s, $m)) {
            return (string) (2018 + (int) $m[1]);
        }

        if (preg_match('/平成(\d{1,2})年?/u', $s, $m)) {
            return (string) (1988 + (int) $m[1]);
        }

        if (preg_match('/昭和(\d{1,2})年?/u', $s, $m)) {
            return (string) (1925 + (int) $m[1]);
        }

        if (preg_match('/^(\d{1,2})年?$/u', $s, $m)) {
            $year = (int) $m[1];

            return (string) ($year <= 29 ? 2000 + $year : 1900 + $year);
        }

        return $s;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function normalizeAddressGroup(array &$data, string $zipField, string $addr1Field, ?string $addr2Field = null): void
    {
        if (! array_key_exists($zipField, $data) && ! array_key_exists($addr1Field, $data)) {
            return;
        }

        $zipText = $this->clean($data[$zipField] ?? null);
        $addr1 = $this->clean($data[$addr1Field] ?? null);
        $addr2 = $addr2Field !== null ? $this->clean($data[$addr2Field] ?? null) : null;

        if ($zipText !== null) {
            $extractedZip = $this->extractZip($zipText);
            if ($extractedZip !== null) {
                $data[$zipField] = $extractedZip;
                $rest = $this->stripZip($zipText);
                if ($rest !== null) {
                    $addr1 = $this->mergeAddress($addr1, $rest);
                }
            } else {
                $data[$zipField] = null;
                $addr1 = $this->mergeAddress($addr1, $zipText);
            }
        } else {
            $data[$zipField] = null;
        }

        foreach ([[$addr1Field, $addr1], [$addr2Field, $addr2]] as [$field, $value]) {
            if ($field === null || $value === null) {
                continue;
            }

            $extractedZip = $this->extractZip($value);
            if ($extractedZip === null) {
                continue;
            }

            if (empty($data[$zipField])) {
                $data[$zipField] = $extractedZip;
            }

            $cleanedAddress = $this->stripZip($value);
            if ($field === $addr1Field) {
                $addr1 = $cleanedAddress;
            } else {
                $addr2 = $cleanedAddress;
            }
        }

        if (($addr1 === null || $addr1 === '') && $addr2 !== null && $addr2 !== '') {
            $addr1 = $addr2;
            $addr2 = null;
        }

        $data[$addr1Field] = $addr1;
        if ($addr2Field !== null) {
            $data[$addr2Field] = $addr2;
        }
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $s = preg_replace('/[ \t\x{3000}]+/u', ' ', $s) ?: $s;
        $s = preg_replace('/^\s+|\s+$/u', '', $s) ?: $s;

        return $s === '' ? null : $s;
    }

    private function extractZip(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = mb_convert_kana($value, 'n', 'UTF-8');
        if (! preg_match('/(\d{3})[-ー－―‐]?\s*(\d{4})/u', $s, $m)) {
            return null;
        }

        return $m[1] . '-' . $m[2];
    }

    private function stripZip(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = mb_convert_kana($value, 'n', 'UTF-8');
        $s = preg_replace('/〒?\s*\d{3}[-ー－―‐]?\s*\d{4}/u', '', $s) ?: $s;
        $s = preg_replace('/^[\s,，、。:：-]+|[\s,，、。:：-]+$/u', '', $s) ?: $s;
        $s = preg_replace('/[ \t\x{3000}]+/u', ' ', $s) ?: $s;
        $s = trim($s);

        return $s === '' ? null : $s;
    }

    private function mergeAddress(?string $current, string $addition): string
    {
        $addition = trim($addition);
        if ($current === null || $current === '') {
            return $addition;
        }

        if (str_contains($current, $addition)) {
            return $current;
        }

        return trim($current . ' ' . $addition);
    }
}
