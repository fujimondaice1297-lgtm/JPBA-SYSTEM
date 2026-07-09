<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProBowlerSearchScopeService
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OVERSEAS = 'overseas';
    public const STATUS_RETIRED = 'retired';

    /**
     * @return array<string,string>
     */
    public function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => '現役選手',
            self::STATUS_OVERSEAS => '海外プロ',
            self::STATUS_RETIRED => '退会者',
        ];
    }

    public function normalizeStatus(?string $value): string
    {
        $value = trim((string) $value);

        return in_array($value, [self::STATUS_ACTIVE, self::STATUS_OVERSEAS, self::STATUS_RETIRED], true)
            ? $value
            : self::STATUS_ACTIVE;
    }

    /**
     * @param Builder<\App\Models\ProBowler> $query
     */
    public function applyStatus(Builder $query, string $status): void
    {
        $status = $this->normalizeStatus($status);
        $retiredNames = $this->retiredMembershipNames();

        if ($status === self::STATUS_RETIRED) {
            $query->where(function ($q) use ($retiredNames) {
                $q->where('pro_bowlers.is_active', false);

                if (! empty($retiredNames)) {
                    $q->orWhereIn('pro_bowlers.membership_type', $retiredNames);
                }
            });

            return;
        }

        $query->where('pro_bowlers.is_active', true);
        $this->excludeRetired($query, $retiredNames);

        if ($status === self::STATUS_OVERSEAS) {
            $this->onlyOverseasOrHonorary($query);

            return;
        }

        $this->excludeOverseasOrHonorary($query);
    }

    /**
     * @param Builder<\App\Models\ProBowler> $query
     * @param array<int,string> $retiredNames
     */
    private function excludeRetired(Builder $query, array $retiredNames): void
    {
        if (empty($retiredNames)) {
            return;
        }

        $query->where(function ($q) use ($retiredNames) {
            $q->whereNull('pro_bowlers.membership_type')
                ->orWhereNotIn('pro_bowlers.membership_type', $retiredNames);
        });
    }

    /**
     * @param Builder<\App\Models\ProBowler> $query
     */
    private function onlyOverseasOrHonorary(Builder $query): void
    {
        $query->where(function ($q) {
            $q->where('pro_bowlers.member_class', 'honorary_or_overseas')
                ->orWhereIn('pro_bowlers.membership_type', ['名誉プロ・海外プロ', '海外'])
                ->orWhereHas('district', fn ($district) => $district->where('label', '海外'));
        });
    }

    /**
     * @param Builder<\App\Models\ProBowler> $query
     */
    private function excludeOverseasOrHonorary(Builder $query): void
    {
        $query->where(function ($q) {
            $q->whereNull('pro_bowlers.member_class')
                ->orWhere('pro_bowlers.member_class', '<>', 'honorary_or_overseas');
        });

        $query->where(function ($q) {
            $q->whereNull('pro_bowlers.membership_type')
                ->orWhereNotIn('pro_bowlers.membership_type', ['名誉プロ・海外プロ', '海外']);
        });

        $query->where(function ($q) {
            $q->whereDoesntHave('district', fn ($district) => $district->where('label', '海外'))
                ->orWhereNull('pro_bowlers.district_id');
        });
    }

    /**
     * @return array<int,string>
     */
    private function retiredMembershipNames(): array
    {
        if (! Schema::hasTable('kaiin_status')) {
            return ['死亡', '除名', '退会届', '退会員'];
        }

        $names = DB::table('kaiin_status')
            ->where('is_retired', true)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return ! empty($names) ? $names : ['死亡', '除名', '退会届', '退会員'];
    }
}
