<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function about(): View
    {
        return view('public.about', [
            'publicConfig' => config('jpba_public', []),
            'association' => config('jpba_public.association', []),
        ]);
    }

    public function schedule(Request $request): View
    {
        $availableYears = $this->availableScheduleYears();
        $requestedYear = $request->integer('year');
        $year = $requestedYear > 0 ? $requestedYear : now()->year;

        if (!in_array($year, $availableYears, true) && !empty($availableYears)) {
            $year = $availableYears[0];
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $tournaments = Tournament::query()
            ->with(['files' => function ($query) {
                $query->where('visibility', 'public')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($nested) use ($start, $end) {
                        $nested->where('start_date', '<=', $end)
                            ->where(function ($range) use ($start) {
                                $range->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $start);
                            });
                    });
            })
            ->orderBy('start_date')
            ->get()
            ->map(fn (Tournament $tournament) => $this->formatTournamentScheduleRow($tournament));

        $manualEvents = CalendarEvent::query()
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($nested) use ($start, $end) {
                        $nested->where('start_date', '<=', $end)
                            ->where(function ($range) use ($start) {
                                $range->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $start);
                            });
                    });
            })
            ->orderBy('start_date')
            ->get()
            ->map(fn (CalendarEvent $event) => $this->formatManualScheduleRow($event));

        $scheduleRows = $tournaments
            ->concat($manualEvents)
            ->sortBy(['sort_date', 'title'])
            ->values();

        return view('public.schedule', [
            'publicConfig' => config('jpba_public', []),
            'year' => $year,
            'availableYears' => $availableYears,
            'scheduleRows' => $scheduleRows,
            'groupedScheduleRows' => $scheduleRows->groupBy('month'),
        ]);
    }

    private function availableScheduleYears(): array
    {
        $years = collect();

        Tournament::query()
            ->select(['start_date', 'end_date'])
            ->where(function ($query) {
                $query->whereNotNull('start_date')
                    ->orWhereNotNull('end_date');
            })
            ->get()
            ->each(function (Tournament $tournament) use ($years) {
                $this->pushYear($years, $tournament->start_date);
                $this->pushYear($years, $tournament->end_date);
            });

        CalendarEvent::query()
            ->select(['start_date', 'end_date'])
            ->where(function ($query) {
                $query->whereNotNull('start_date')
                    ->orWhereNotNull('end_date');
            })
            ->get()
            ->each(function (CalendarEvent $event) use ($years) {
                $this->pushYear($years, $event->start_date);
                $this->pushYear($years, $event->end_date);
            });

        return $years->unique()->sortDesc()->values()->all();
    }

    private function pushYear(Collection $years, mixed $date): void
    {
        if ($date) {
            $years->push((int) Carbon::parse($date)->year);
        }
    }

    private function formatTournamentScheduleRow(Tournament $tournament): array
    {
        $start = $tournament->start_date ? Carbon::parse($tournament->start_date) : null;
        $end = $tournament->end_date ? Carbon::parse($tournament->end_date) : $start;

        return [
            'type' => 'tournament',
            'type_label' => $tournament->officialTypeLabel . ' / ' . $tournament->genderLabel,
            'title' => $tournament->name,
            'venue' => $tournament->venue_name,
            'period' => $this->formatPeriod($start, $end),
            'month' => (int) ($start ?: $end)?->month,
            'sort_date' => ($start ?: $end)?->toDateString() ?? '9999-12-31',
            'links' => $tournament->files
                ->take(3)
                ->map(fn ($file) => [
                    'label' => $file->title ?: 'PDF',
                    'url' => asset('storage/' . ltrim((string) $file->file_path, '/')),
                ])
                ->values()
                ->all(),
        ];
    }

    private function formatManualScheduleRow(CalendarEvent $event): array
    {
        $start = $event->start_date ? Carbon::parse($event->start_date) : null;
        $end = $event->end_date ? Carbon::parse($event->end_date) : $start;

        return [
            'type' => 'calendar_event',
            'type_label' => $event->kindLabel,
            'title' => $event->title,
            'venue' => $event->venue,
            'period' => $this->formatPeriod($start, $end),
            'month' => (int) ($start ?: $end)?->month,
            'sort_date' => ($start ?: $end)?->toDateString() ?? '9999-12-31',
            'links' => [],
        ];
    }

    private function formatPeriod(?Carbon $start, ?Carbon $end): string
    {
        if (!$start && !$end) {
            return '';
        }

        $startText = $start?->format('Y/n/j') ?? '';
        $endText = $end?->format('Y/n/j') ?? '';

        if ($startText !== '' && $endText !== '' && $startText !== $endText) {
            return "{$startText}-{$endText}";
        }

        return $startText ?: $endText;
    }
}
