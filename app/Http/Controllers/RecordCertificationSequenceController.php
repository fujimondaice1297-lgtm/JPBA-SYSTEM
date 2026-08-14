<?php

namespace App\Http\Controllers;

use App\Models\RecordCertificationSequence;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecordCertificationSequenceController extends Controller
{
    public function index()
    {
        $sequences = RecordCertificationSequence::query()
            ->orderBy('record_type')
            ->orderBy('gender')
            ->get()
            ->keyBy(fn (RecordCertificationSequence $row) => $row->record_type . ':' . $row->gender);

        return view('record_types.sequences', compact('sequences'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'record_type' => ['required', Rule::in(['perfect', 'seven_ten', 'eight_hundred'])],
            'gender' => ['required', Rule::in(['M', 'F'])],
            'next_number' => ['required', 'integer', 'min:1'],
            'prefix' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:100'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        RecordCertificationSequence::query()->updateOrCreate(
            [
                'record_type' => $validated['record_type'],
                'gender' => $validated['gender'],
            ],
            [
                'next_number' => $validated['next_number'],
                'prefix' => $validated['prefix'] ?? null,
                'suffix' => $validated['suffix'] ?? null,
                'is_enabled' => $request->boolean('is_enabled'),
            ]
        );

        return back()->with('success', '次回公認番号を保存しました。');
    }
}
