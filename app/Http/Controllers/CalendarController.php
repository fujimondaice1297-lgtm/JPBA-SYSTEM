<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\CalendarEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Models\CalendarDay;

class CalendarController extends Controller
{
    /** Carbonに安全変換（null/文字列/Carbon全部OK） */
    private function c($v): ?Carbon
    {
        if ($v instanceof Carbon) return $v->copy();
        if (!$v) return null;
        return Carbon::parse($v);
    }

    /** その日のセル背景クラス（優先度：手入力 or 承認/その他 ＞ 男女 ＞ 混合/未設定） */
    private function bgClassForDay(array $eventsOfDay): string
    {
        foreach ($eventsOfDay as $ev) {
            // 手入力は薄紫
            if ($ev instanceof CalendarEvent) return 'bg-manual';
            // 承認/その他の大会も薄紫
            if ($ev instanceof Tournament && in_array($ev->official_type ?? 'official', ['approved','other'], true)) {
                return 'bg-manual';
            }
        }
        foreach ($eventsOfDay as $ev) {
            if ($ev instanceof Tournament) {
                $g = $ev->gender ?? 'X';
                if ($g === 'M') return 'bg-men';
                if ($g === 'F') return 'bg-women';
            }
        }
        return count($eventsOfDay) ? 'bg-mixed' : '';
    }

    /** 年間一覧（大会 + 手入力イベントを月別にまとめて表示） */
    public function annual(?int $year = null)
    {
        $year = (int)($year ?? request('year') ?? now()->year);

        // v2 をつけて旧キャッシュと区別
        $data = Cache::remember("calendar:annual:v2:$year", 3600, function () use ($year) {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end   = Carbon::create($year, 12, 31)->endOfDay();

            // 大会（この年にかすっていれば対象）
            $tournaments = Tournament::query()
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($qq) use ($start, $end) {
                          $qq->where('start_date', '<=', $end)
                             ->where(function ($q3) use ($start) {
                                 $q3->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $start);
                             });
                      });
                })
                ->orderBy('start_date')
                ->get();

            // 手入力イベント（CalendarEvent）
            $manuals = CalendarEvent::query()
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($qq) use ($start, $end) {
                          $qq->where('start_date', '<=', $end)
                             ->where(function ($q3) use ($start) {
                                 $q3->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $start);
                             });
                      });
                })
                ->orderBy('start_date')
                ->get();

            // まとめて月別にグループ化（基準は開始日、無ければ終了日）
            $all = $tournaments->concat($manuals)
                ->sortBy(function ($t) {
                    $s = $this->c($t->start_date);
                    $e = $this->c($t->end_date);
                    return ($s ?: $e)?->timestamp ?? PHP_INT_MAX;
                })
                ->values();

            $grouped = $all->groupBy(function ($t) {
                $s = $this->c($t->start_date);
                $e = $this->c($t->end_date);
                $base = $s ?: $e;
                return $base ? (int)$base->month : 0;
            });

            return [
                'year'    => $year,
                'grouped' => $grouped, // ← 大会も手入力もこの中に入る
            ];
        });

        return view('calendar.annual', $data);
    }

    /** 月間（週：月曜→日曜） */
    public function monthly(int $year, int $month)
    {
        $first = Carbon::create($year, $month, 1);
        $gridStart = $first->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd   = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $payload = Cache::remember("calendar:monthly:$year:$month", 1800, function () use ($year, $month, $gridStart, $gridEnd) {
            $start = $gridStart->copy();
            $end   = $gridEnd->copy();

            // 大会
            $events = Tournament::query()
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($qq) use ($start, $end) {
                          $qq->where('start_date', '<=', $end)
                             ->where(function ($q3) use ($start) {
                                 $q3->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $start);
                             });
                      });
                })
                ->orderBy('start_date')
                ->get();

            // 手入力（プロテスト/承認/その他等）
            $manuals = CalendarEvent::query()
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                      ->orWhereBetween('end_date', [$start, $end])
                      ->orWhere(function ($qq) use ($start, $end) {
                          $qq->where('start_date', '<=', $end)
                             ->where(function ($q3) use ($start) {
                                 $q3->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $start);
                             });
                      });
                })
                ->orderBy('start_date')
                ->get();

            $events = $events->concat($manuals);

            // 日付→イベント配列、日付→背景クラス
            $map = [];
            $bgMap = [];
            for ($d = $start->copy(); $d <= $end; $d->addDay()) {
                $key = $d->toDateString();
                $map[$key] = [];
                $bgMap[$key] = '';
            }

            foreach ($events as $e) {
                $sd = $this->c($e->start_date);
                $ed = $this->c($e->end_date) ?: $sd;
                if (!$sd) continue;

                for ($d = $sd->copy(); $d <= $ed; $d->addDay()) {
                    $key = $d->toDateString();
                    if (isset($map[$key])) $map[$key][] = $e;
                }
            }

            foreach ($map as $dateKey => $evts) {
                $bgMap[$dateKey] = $this->bgClassForDay($evts);
            }

            // 祝日・六曜を読み込んでビューへ渡す（キー: YYYY-mm-dd）
            $dayMeta = [];
            $dayRows = CalendarDay::whereBetween('date', [$start, $end])->get();
            foreach ($dayRows as $row) {
                $dayMeta[$row->date->toDateString()] = [
                    'is_holiday'   => (bool)$row->is_holiday,
                    'holiday_name' => $row->holiday_name,
                    'rokuyou'      => $row->rokuyou,
                ];
            }

            return [
                'year'      => $year,
                'month'     => $month,
                'gridStart' => $gridStart,
                'gridEnd'   => $gridEnd,
                'map'       => $map,
                'bgMap'     => $bgMap,
                'dayMeta'   => $dayMeta,
            ];
        });

        return view('calendar.monthly', $payload);
    }

    /** PDF: 年間 */
    public function annualPdf(int $year)
    {
        $view = $this->annual($year)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($view)->setPaper('a4', 'portrait');
        return $pdf->stream("calendar_annual_{$year}.pdf");
    }

    /** PDF: 月間 */
    public function monthlyPdf(int $year, int $month)
    {
        $view = $this->monthly($year, $month)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($view)->setPaper('a4', 'landscape');
        return $pdf->stream("calendar_monthly_{ $year }_{ $month }.pdf");
    }
}
