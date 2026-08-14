<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BallAnnualRegistration;
use App\Models\Tournament;
use App\Support\ManagementNavigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class AdminHomeController extends Controller
{
    public function index(ManagementNavigation $navigation): View
    {
        $user = auth()->user();
        abort_unless($user && ($user->isEditor() || $user->isAdmin()), 403);

        $today = today();
        $currentYear = (int) $today->format('Y');

        $tournaments = Tournament::query()
            ->withCount([
                'entries as confirmed_entries_count' => fn (Builder $query) => $query->where('status', 'entry'),
            ])
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today->copy()->subDays(14));
            })
            ->orderByRaw(
                'CASE WHEN start_date >= ? THEN 0 ELSE 1 END',
                [$today->toDateString()]
            )
            ->orderBy('start_date')
            ->limit(6)
            ->get();

        if ($tournaments->isEmpty()) {
            $tournaments = Tournament::query()
                ->withCount([
                    'entries as confirmed_entries_count' => fn (Builder $query) => $query->where('status', 'entry'),
                ])
                ->orderByDesc('start_date')
                ->limit(6)
                ->get();
        }

        $metrics = [
            [
                'label' => '30日以内の大会',
                'value' => Tournament::query()
                    ->whereBetween('start_date', [$today, $today->copy()->addDays(30)])
                    ->count(),
                'note' => '開催準備を確認',
                'route' => 'tournaments.index',
            ],
            [
                'label' => '準備中の大会',
                'value' => Tournament::query()->where('setup_status', 'draft')->count(),
                'note' => '設定未完了を確認',
                'route' => 'tournaments.index',
            ],
            [
                'label' => 'ボール承認待ち',
                'value' => BallAnnualRegistration::query()
                    ->where('registration_year', $currentYear)
                    ->where('status', BallAnnualRegistration::STATUS_SUBMITTED)
                    ->count(),
                'note' => $currentYear . '年度申請',
                'route' => 'ball_annual_registrations.index',
            ],
            [
                'label' => '今年度の大会',
                'value' => Tournament::query()->where('year', $currentYear)->count(),
                'note' => $currentYear . '年度',
                'route' => 'tournaments.index',
            ],
        ];

        return view('admin.dashboard', [
            'managementGroups' => $navigation->groups($user),
            'quickActions' => $navigation->quickActions($user),
            'metrics' => $metrics,
            'tournaments' => $tournaments,
            'currentYear' => $currentYear,
        ]);
    }
}
