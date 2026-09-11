<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\JapanOpenFormatService;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class JapanOpenFormatController extends Controller
{
    public function store(Request $request, JapanOpenFormatService $service)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'edition_no' => ['nullable', 'integer', 'between:1,999'],
            'name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'ball_registration_limit' => ['required', 'integer', 'between:1,99'],
        ]);

        try {
            $report = $service->setup($data, true);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['japan_open' => $exception->getMessage()])->withInput();
        }

        $overviewId = $report['component_ids']['overview'] ?? null;
        $message = sprintf(
            '%d年度ジャパンオープンの標準構成を作成しました（%d競技、チーム・ダブルス・オールエベンツ合算設定済み）。',
            (int) $data['year'],
            (int) $report['component_count'],
        );

        return $overviewId
            ? redirect()->route('tournaments.edit', Tournament::query()->findOrFail($overviewId))->with('success', $message)
            : redirect()->route('tournament_templates.index')->with('success', $message);
    }
}
