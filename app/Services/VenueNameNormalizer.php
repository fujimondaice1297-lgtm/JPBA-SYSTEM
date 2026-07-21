<?php

namespace App\Services;

class VenueNameNormalizer
{
    public function normalize(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $name = mb_convert_kana($name, 'asKV', 'UTF-8');
        $name = mb_strtolower(str_replace(['･', '／'], ['・', '/'], $name), 'UTF-8');

        return preg_replace('/[\s・\/()（）\-]+/u', '', $name) ?? '';
    }
}
