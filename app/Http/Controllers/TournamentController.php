<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tournament;
use Carbon\Carbon;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', $request->start_date);
        }

        if ($request->filled('venue_name')) {
            $query->where('venue_name', 'like', '%' . $request->venue_name . '%');
        }

        $tournaments = $query->get();

        return view('tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return view('tournaments.create');
    }

    public function show($id)
    {
        $tournament = Tournament::findOrFail($id);
        return view('tournaments.show', compact('tournament'));
    }

    public function edit($id)
    {
        $tournament = Tournament::findOrFail($id);
        return view('tournaments.edit', compact('tournament'));
    }

    public function update(Request $request, $id)
    {
        $tournament = Tournament::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'venue_name' => 'nullable|string',
            'venue_address' => 'nullable|string',
            'venue_tel' => 'nullable|string',
            'venue_fax' => 'nullable|string',
            'host' => 'nullable|string',
            'special_sponsor' => 'nullable|string',
            'support' => 'nullable|string',
            'sponsor' => 'nullable|string',
            'supervisor' => 'nullable|string',
            'authorized_by' => 'nullable|string',
            'broadcast' => 'nullable|string',
            'streaming' => 'nullable|string',
            'prize' => 'nullable|string',
            'audience' => 'nullable|string',
            'entry_conditions' => 'nullable|string',
            'materials' => 'nullable|string',
            'previous_event' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'gender'        => 'required|in:M,F,X',
            'official_type' => 'required|in:official,approved,other',
            'entry_start'   => 'nullable|date',
            'entry_end'     => 'nullable|date|after_or_equal:entry_start',
            'inspection_required' => 'nullable|boolean',
        ]);

        // 時刻補完
        $validated = array_merge($validated, [
            'entry_start' => $request->filled('entry_start')
                ? Carbon::parse($request->input('entry_start'))->setTime(10, 0)
                : null,
            'entry_end' => $request->filled('entry_end')
                ? Carbon::parse($request->input('entry_end'))->setTime(23, 59)
                : null,
            'inspection_required' => $request->boolean('inspection_required'),
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('posters', 'public');
        }

        $tournament->update($validated);

        return redirect()
            ->route('tournaments.show', $tournament->id)
            ->with('success', '大会情報を更新しました。');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'venue_name' => 'nullable|string',
            'venue_address' => 'nullable|string',
            'venue_tel' => 'nullable|string',
            'venue_fax' => 'nullable|string',
            'host' => 'nullable|string',
            'special_sponsor' => 'nullable|string',
            'support' => 'nullable|string',
            'sponsor' => 'nullable|string',
            'supervisor' => 'nullable|string',
            'authorized_by' => 'nullable|string',
            'broadcast' => 'nullable|string',
            'streaming' => 'nullable|string',
            'prize' => 'nullable|string',
            'audience' => 'nullable|string',
            'entry_conditions' => 'nullable|string',
            'materials' => 'nullable|string',
            'previous_event' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'gender'        => 'required|in:M,F,X',
            'official_type' => 'required|in:official,approved,other',
            'entry_start'   => 'nullable|date',
            'entry_end'     => 'nullable|date|after_or_equal:entry_start',
            'inspection_required' => 'nullable|boolean',
        ]);

        $validated = array_merge($validated, [
            'entry_start' => $request->filled('entry_start')
                ? Carbon::parse($request->input('entry_start'))->setTime(10, 0)
                : null,
            'entry_end' => $request->filled('entry_end')
                ? Carbon::parse($request->input('entry_end'))->setTime(23, 59)
                : null,
            'inspection_required' => $request->boolean('inspection_required'),
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('posters', 'public');
        }

        Tournament::create($validated);

        return redirect()->route('tournaments.index')->with('success', '大会が登録されました');
    }
}
