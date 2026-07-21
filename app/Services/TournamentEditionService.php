<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentEdition;
use Carbon\Carbon;

class TournamentEditionService
{
    public function attach(Tournament $tournament, ?string $seasonKey = null): ?TournamentEdition
    {
        if (! $tournament->tournament_series_id) {
            if ($tournament->tournament_edition_id !== null) {
                $tournament->forceFill(['tournament_edition_id' => null])->save();
            }

            return null;
        }

        $year = (int) ($tournament->year
            ?: ($tournament->start_date ? Carbon::parse($tournament->start_date)->year : now()->year));
        $seasonKey = trim((string) $seasonKey) ?: 'annual';

        $edition = TournamentEdition::query()->firstOrCreate(
            [
                'tournament_series_id' => $tournament->tournament_series_id,
                'year' => $year,
                'season_key' => $seasonKey,
            ],
            [
                'name' => $year.' '.($tournament->series?->name ?: $tournament->name),
                'status' => 'draft',
                'start_date' => $tournament->start_date,
                'end_date' => $tournament->end_date,
            ]
        );

        $edition->fill([
            'start_date' => $this->earlierDate($edition->start_date, $tournament->start_date),
            'end_date' => $this->laterDate($edition->end_date, $tournament->end_date),
        ])->save();

        $tournament->forceFill(['tournament_edition_id' => $edition->id])->save();

        return $edition;
    }

    private function earlierDate($current, $candidate): mixed
    {
        if (! $current) {
            return $candidate;
        }
        if (! $candidate) {
            return $current;
        }

        return Carbon::parse($candidate)->lt(Carbon::parse($current)) ? $candidate : $current;
    }

    private function laterDate($current, $candidate): mixed
    {
        if (! $current) {
            return $candidate;
        }
        if (! $candidate) {
            return $current;
        }

        return Carbon::parse($candidate)->gt(Carbon::parse($current)) ? $candidate : $current;
    }
}
