<?php

namespace App\Support;

final class PublicLicenseNumber
{
    /**
     * Convert an internal JPBA identifier to the number shown to the public.
     *
     * Examples: M00001297 => 1297, F00000599 => 0599,
     * M000T012 => T012. Non-JPBA identifiers are kept unchanged.
     */
    public static function format(mixed $value, string $fallback = '-'): string
    {
        $raw = mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $value)) ?? '');

        if ($raw === '') {
            return $fallback;
        }

        if (str_starts_with($raw, 'AMATEUR-') || $raw === 'アマ') {
            return 'アマ';
        }

        if (preg_match('/^[MF]0*T0*(\d{1,3})$/', $raw, $matches) === 1
            || preg_match('/^T0*(\d{1,3})$/', $raw, $matches) === 1) {
            return 'T'.str_pad($matches[1], 3, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^[MF]0*(\d{1,4})$/', $raw, $matches) === 1) {
            return str_pad($matches[1], 4, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^\d+$/', $raw) === 1) {
            return str_pad(substr($raw, -4), 4, '0', STR_PAD_LEFT);
        }

        return $raw;
    }
}
