<?php

namespace App\Services;

use App\Models\Tournament;

final class TournamentClassificationPolicyService
{
    /**
     * 女子トーナメント出場優先順位決定戦は出場順を決める選考会であり、
     * 公式ポイント・STポイント・賞金・タイトルの集計対象にしない。
     *
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    public function normalizeTournamentAttributes(array $attributes): array
    {
        if (! $this->isWomensPriorityDetermination(
            (string) ($attributes['name'] ?? ''),
            (string) ($attributes['gender'] ?? ''),
        )) {
            return $attributes;
        }

        $attributes['counts_for_official_points'] = false;
        $attributes['counts_for_prize'] = false;
        $attributes['title_scope'] = 'none';
        $attributes['title_category'] = 'excluded';

        return $attributes;
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function normalizeResultOutputInput(Tournament $tournament, array $input): array
    {
        if (! $this->isWomensPriorityDetermination($tournament->name, $tournament->gender)) {
            return $input;
        }

        $input['counts_for_official_points'] = false;
        $input['counts_for_season_trial_points'] = false;
        $input['counts_for_prize'] = false;
        $input['title_scope'] = 'none';
        $input['produces_entry_priority'] = true;

        return $input;
    }

    public function isWomensPriorityDetermination(string $name, string $gender): bool
    {
        return mb_strtoupper(trim($gender)) === 'F'
            && str_contains($name, '優先順位決定戦');
    }
}
