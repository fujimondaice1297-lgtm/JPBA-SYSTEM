<?php

namespace App\Http\Controllers;

use App\Models\ManagedPublicPage;
use App\Models\RecordType;
use Illuminate\View\View;

class OfficialRecordsController extends Controller
{
    public function index(): View
    {
        $recordCounts = RecordType::query()
            ->selectRaw('record_type, count(*) as aggregate')
            ->groupBy('record_type')
            ->pluck('aggregate', 'record_type');

        return view('public.records.index', [
            'publicConfig' => config('jpba_public', []),
            'managedPages' => ManagedPublicPage::query()
                ->published()
                ->whereIn('slug', ['permanent-seed', 'hall-of-fame', 'official-high-records', 'pro-wappen'])
                ->get()
                ->keyBy('slug'),
            'recordCounts' => $recordCounts,
        ]);
    }
}
