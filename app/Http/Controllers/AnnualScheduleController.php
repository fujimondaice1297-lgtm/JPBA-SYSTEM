<?php

namespace App\Http\Controllers;

use App\Models\AnnualSchedule;
use App\Models\Tournament;
use App\Services\OfficialAnnualScheduleImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnualScheduleController extends Controller
{
    public function edit(int $year): View
    {
        abort_unless($year >= 2000 && $year <= 2100, 404);

        $schedule = AnnualSchedule::query()->firstOrCreate(
            ['year' => $year],
            [
                'title' => 'トーナメント年間予定表',
                'notice' => '※都合により、日時・会場等変更になる場合があります。',
                'status' => AnnualSchedule::STATUS_DRAFT,
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]
        )->load('rows.tournament');

        $tournaments = Tournament::query()
            ->where(function ($query) use ($year): void {
                $query->where('year', $year)->orWhereYear('start_date', $year);
            })
            ->orderBy('start_date')
            ->orderBy('name')
            ->get(['id', 'name', 'start_date', 'venue_name']);

        return view('annual_schedules.edit', [
            'schedule' => $schedule,
            'groupedRows' => $schedule->rows->groupBy('month'),
            'tournaments' => $tournaments,
            'availableYears' => $this->availableYears($year),
        ]);
    }

    public function update(Request $request, int $year): RedirectResponse
    {
        $schedule = AnnualSchedule::query()->where('year', $year)->firstOrFail();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'source_updated_on' => 'nullable|date',
            'source_url' => 'nullable|url|max:2000',
            'notice' => 'nullable|string|max:2000',
            'rows' => 'nullable|array|max:300',
            'rows.*.id' => ['nullable', 'integer', Rule::exists('annual_schedule_rows', 'id')->where('annual_schedule_id', $schedule->id)],
            'rows.*.month' => 'required|integer|min:1|max:12',
            'rows.*.start_date' => 'nullable|date',
            'rows.*.end_date' => 'nullable|date|after_or_equal:rows.*.start_date',
            'rows.*.date_label' => 'nullable|string|max:255',
            'rows.*.title' => 'nullable|string|max:2000',
            'rows.*.eligibility' => 'nullable|string|max:255',
            'rows.*.region' => 'nullable|string|max:100',
            'rows.*.venue' => 'nullable|string|max:2000',
            'rows.*.point_mark' => 'nullable|string|max:10',
            'rows.*.average_mark' => 'nullable|string|max:10',
            'rows.*.prize_mark' => 'nullable|string|max:10',
            'rows.*.title_mark' => 'nullable|string|max:10',
            'rows.*.note' => 'nullable|string|max:2000',
            'rows.*.row_type' => 'nullable|in:event,qualifier,note,placeholder',
            'rows.*.tournament_id' => 'nullable|integer|distinct|exists:tournaments,id',
            'rows.*.delete' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($schedule, $validated): void {
            $schedule->fill([
                'title' => $validated['title'],
                'source_updated_on' => $validated['source_updated_on'] ?? null,
                'source_url' => $validated['source_url'] ?? null,
                'notice' => $validated['notice'] ?? null,
                'updated_by_user_id' => auth()->id(),
            ])->save();

            foreach (array_values($validated['rows'] ?? []) as $order => $attributes) {
                $row = !empty($attributes['id'])
                    ? $schedule->rows()->whereKey((int) $attributes['id'])->firstOrFail()
                    : $schedule->rows()->make();

                if (!empty($attributes['delete'])) {
                    if ($row->exists) {
                        $row->delete();
                    }
                    continue;
                }

                unset($attributes['id'], $attributes['delete']);
                if ($this->rowIsEmpty($attributes)) {
                    continue;
                }

                $attributes['sort_order'] = ($order + 1) * 10;
                $attributes['source_type'] = $row->source_type ?: 'manual';
                $row->fill($attributes)->save();
            }
        });

        return redirect()->route('annual_schedules.edit', $year)
            ->with('success', $year . '年の年間予定表を保存しました。');
    }

    public function publish(int $year): RedirectResponse
    {
        $schedule = AnnualSchedule::query()->where('year', $year)->firstOrFail();
        abort_if(!$schedule->rows()->where(function ($query): void {
            $query->whereNotNull('title')->orWhereNotNull('note');
        })->exists(), 422, '公開できる予定がありません。');

        $schedule->update([
            'status' => AnnualSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
            'updated_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', $year . '年の予定表を一般公開しました。');
    }

    public function unpublish(int $year): RedirectResponse
    {
        AnnualSchedule::query()->where('year', $year)->firstOrFail()->update([
            'status' => AnnualSchedule::STATUS_DRAFT,
            'published_at' => null,
            'updated_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', $year . '年の予定表を非公開にしました。');
    }

    public function importOfficial(
        Request $request,
        int $year,
        OfficialAnnualScheduleImportService $service,
    ): RedirectResponse {
        abort_unless($year === 2026, 404);
        $service->import2026($request->boolean('replace'));

        return redirect()->route('annual_schedules.edit', $year)
            ->with('success', 'JPBA公式PDF（2026年7月1日現在）の内容を取り込みました。');
    }

    public function pdf(int $year)
    {
        $schedule = AnnualSchedule::query()
            ->with('rows.tournament')
            ->where('year', $year)
            ->firstOrFail();

        if ($schedule->status !== AnnualSchedule::STATUS_PUBLISHED) {
            $user = auth()->user();
            abort_unless($user && ($user->isEditor() || $user->isAdmin()), 404);
        }

        return Pdf::loadView('annual_schedules.pdf', [
            'schedule' => $schedule,
            'groupedRows' => $schedule->rows->groupBy('month'),
        ])->setPaper('a4', 'portrait')->stream("jpba_schedule_{$year}.pdf");
    }

    private function rowIsEmpty(array $attributes): bool
    {
        foreach (['date_label', 'title', 'eligibility', 'region', 'venue', 'note', 'tournament_id'] as $key) {
            if (trim((string) ($attributes[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function availableYears(int $currentYear): array
    {
        return AnnualSchedule::query()->pluck('year')
            ->push($currentYear)
            ->push(now()->year)
            ->push(now()->addYear()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
