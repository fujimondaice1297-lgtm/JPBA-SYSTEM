<?php

namespace App\Services;

use App\Models\ApprovedBall;
use Illuminate\Support\Collection;

class BallCatalogBrandService
{
    /**
     * 国内代理店カタログのブランドを先頭にし、USBCだけにあるブランドを分離する。
     *
     * @param  Collection<int, ApprovedBall>  $balls
     * @return array{0: Collection<int, string>, 1: Collection<int, string>}
     */
    public function registrationBrandGroups(Collection $balls): array
    {
        $distributorBrands = $this->sortedUniqueBrands(
            $balls->reject(fn (ApprovedBall $ball): bool => $this->isUsbcOnlyBall($ball))
        );
        $usbcOnlyBrands = $this->sortedUniqueBrands(
            $balls->filter(fn (ApprovedBall $ball): bool => $this->isUsbcOnlyBall($ball))
        )->reject(
            fn (string $brand): bool => $distributorBrands->containsStrict($brand)
        )->values();

        return [$distributorBrands, $usbcOnlyBrands];
    }

    /**
     * 国内代理店掲載品は代理店サイトのブランド区分を優先する。
     */
    public function registrationBrand(ApprovedBall $ball): string
    {
        $payload = (array) $ball->source_payload;
        $localBrand = trim((string) ($ball->brand ?: $ball->manufacturer));

        if (($payload['source_type'] ?? null) !== 'usbc_approved_list') {
            return $localBrand;
        }

        $officialBrand = trim((string) ($ball->usbc_matched_brand ?: $localBrand));

        return $this->officialCatalogBrand($officialBrand, (string) $ball->name);
    }

    /**
     * USBCだけに存在する品も、国内代理店で用いるブランド表記へ寄せる。
     */
    public function officialCatalogBrand(string $officialBrand, string $ballName): string
    {
        if (mb_strtoupper(trim($officialBrand), 'UTF-8') === 'ABS') {
            if (preg_match('/NANO\s*DESU/i', $ballName) === 1) {
                return 'NANODESU';
            }
            if (preg_match('/^\s*PRO[\s-]*AM\b/i', $ballName) === 1) {
                return 'PRO-am';
            }
        }

        $key = mb_strtoupper(trim($officialBrand), 'UTF-8');
        $key = preg_replace('/[.\s]+/u', '', $key) ?? $key;

        return match ($key) {
            '900GLOBAL' => '900GLOBAL',
            'MOTIV' => 'MOTIV',
            'HIGHSPORTS' => 'HI-SP',
            'ROTOGRIP' => 'ROTOGRIP',
            'STORM', 'STORM(HIGHSCOREPRODUCTS)' => 'STORM',
            'BRUNSWICK' => 'Brunswick',
            'DEXTER' => 'DEXTER',
            'DV8' => 'DV8',
            'EBONITE' => 'EBONITE',
            'HAMMER' => 'HAMMER',
            'RADICAL' => 'RADICAL',
            'SUNBRIDGECO,LTD' => 'SUNBRIDGE',
            'TRACKINC' => 'TRACK BOWLING',
            default => trim($officialBrand),
        };
    }

    /**
     * @param  Collection<int, ApprovedBall>  $balls
     * @return Collection<int, string>
     */
    private function sortedUniqueBrands(Collection $balls): Collection
    {
        return $balls
            ->map(fn (ApprovedBall $ball): string => trim($ball->registration_brand))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function isUsbcOnlyBall(ApprovedBall $ball): bool
    {
        $payload = (array) $ball->source_payload;

        return ($payload['source_type'] ?? null) === 'usbc_approved_list';
    }
}
