<?php

namespace App\Http\Controllers;

use App\Services\OilPatternCatalogService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class PublicOilPatternController extends Controller
{
    public function index(Request $request, OilPatternCatalogService $catalog): View
    {
        $filters = $request->only(['year', 'classification', 'venue', 'keyword']);
        $allRows = $catalog->rows(publicOnly: true);
        $rows = $catalog->filter($allRows, $filters);
        $page = max(1, $request->integer('page', 1));
        $perPage = 24;
        $patterns = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('public.tournaments.oil-patterns', [
            'publicConfig' => config('jpba_public', []),
            'patterns' => $patterns,
            'years' => $allRows->pluck('year')->filter()->unique()->sortDesc()->values(),
            'venues' => $allRows->pluck('venue_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'filters' => $filters,
        ]);
    }
}
