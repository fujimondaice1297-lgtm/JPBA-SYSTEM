<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Information;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function protest(): View
    {
        return view('public.protest', [
            'publicConfig' => config('jpba_public', []),
            'protestConfig' => config('jpba_public.protest', []),
            'proTestSchedules' => $this->proTestSchedules(),
            'proTestInformations' => $this->proTestInformations(),
        ]);
    }

    public function topics(Request $request): View
    {
        $categories = Information::categories();
        $category = trim((string) $request->query('category', ''));

        if ($category === '' || !in_array($category, $categories, true)) {
            $category = null;
        }

        $topics = Information::query()
            ->active()
            ->public()
            ->with(['files' => function ($query) {
                $query->where('visibility', 'public')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('public.topics', [
            'publicConfig' => config('jpba_public', []),
            'topicsConfig' => config('jpba_public.topics', []),
            'topics' => $topics,
            'categories' => $categories,
            'category' => $category,
        ]);
    }

    public function staticPage(string $page): View
    {
        $pages = config('jpba_public.static_pages', []);

        if (!isset($pages[$page])) {
            abort(404);
        }

        return view('public.static_page', [
            'publicConfig' => config('jpba_public', []),
            'page' => $page,
            'pageConfig' => $pages[$page],
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

    private function proTestSchedules(): Collection
    {
        $rows = collect();

        if (Schema::hasTable('pro_test_schedule')) {
            $rows = DB::table('pro_test_schedule')
                ->select(['id', 'year', 'schedule_name', 'start_date', 'end_date', 'application_start', 'application_end'])
                ->orderByDesc('year')
                ->orderByDesc('start_date')
                ->limit(8)
                ->get();
        }

        $events = CalendarEvent::query()
            ->where('kind', 'pro_test')
            ->orderByDesc('start_date')
            ->limit(8)
            ->get()
            ->map(function (CalendarEvent $event) {
                return (object) [
                    'id' => 'event-' . $event->id,
                    'year' => $event->start_date ? Carbon::parse($event->start_date)->year : null,
                    'schedule_name' => $event->title,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'application_start' => null,
                    'application_end' => null,
                ];
            });

        return $rows
            ->concat($events)
            ->sortByDesc(fn ($row) => ($row->start_date ?: ($row->year ? $row->year . '-01-01' : '0000-01-01')))
            ->values();
    }

    private function proTestInformations(): Collection
    {
        return Information::query()
            ->active()
            ->public()
            ->withCount(['files' => function ($query) {
                $query->where('visibility', 'public');
            }])
            ->where(function ($query) {
                $query->where('title', 'like', '%プロテスト%')
                    ->orWhere('title', 'like', '%受験%')
                    ->orWhere('body', 'like', '%プロテスト%');
            })
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
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
