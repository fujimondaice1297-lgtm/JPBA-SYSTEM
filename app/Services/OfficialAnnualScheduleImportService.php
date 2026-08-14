<?php

namespace App\Services;

use App\Models\AnnualSchedule;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OfficialAnnualScheduleImportService
{
    public function import2026(bool $replace = false): AnnualSchedule
    {
        $path = database_path('data/jpba_2026_annual_schedule.json');
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($data, $replace): AnnualSchedule {
            $schedule = AnnualSchedule::query()->firstOrNew(['year' => (int) $data['year']]);
            if ($schedule->exists && $schedule->rows()->exists() && !$replace) {
                return $schedule->load('rows.tournament');
            }

            $schedule->fill([
                'title' => $data['title'],
                'source_updated_on' => $data['source_updated_on'],
                'source_url' => $data['source_url'],
                'notice' => $data['notice'],
                'status' => AnnualSchedule::STATUS_PUBLISHED,
                'published_at' => now(),
                'created_by_user_id' => $schedule->created_by_user_id ?: auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ])->save();

            if ($replace) {
                $schedule->rows()->delete();
            }

            foreach ($data['rows'] as $index => $attributes) {
                $attributes['sort_order'] = ($index + 1) * 10;
                $attributes['source_type'] = 'official_pdf';
                $attributes['tournament_id'] = $this->matchingTournamentId($attributes);
                $schedule->rows()->create($attributes);
            }

            return $schedule->load('rows.tournament');
        });
    }

    private function matchingTournamentId(array $row): ?int
    {
        if (empty($row['start_date']) || empty($row['title'])) {
            return null;
        }

        $query = Tournament::query()->whereDate('start_date', $row['start_date']);
        if (!empty($row['venue'])) {
            $venues = preg_split('/\R/u', (string) $row['venue']) ?: [];
            $query->where(function ($nested) use ($venues): void {
                foreach ($venues as $venue) {
                    $needle = mb_substr(trim($venue), 0, 8);
                    if ($needle !== '') {
                        $nested->orWhere('venue_name', 'like', '%' . $needle . '%');
                    }
                }
            });
        }

        $candidates = $query->get();
        if ($candidates->count() !== 1) {
            return null;
        }

        return (int) $candidates->first()->id;
    }
}
