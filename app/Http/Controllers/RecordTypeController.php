<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\RecordType;
use App\Services\AchievementRecordService;
use App\Services\AwardCounter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class RecordTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = RecordType::query()
            ->with(['proBowler', 'tournament', 'scoreSeriesDefinition']);

        if ($request->filled('player_identifier')) {
            $query->whereHas('proBowler', function ($query) use ($request): void {
                $keyword = '%' . trim((string) $request->player_identifier) . '%';
                $query->where(function ($query) use ($keyword): void {
                    $query->where('name_kanji', 'like', $keyword)
                        ->orWhere('name_kana', 'like', $keyword)
                        ->orWhere('license_no', 'like', $keyword);
                });
            });
        }

        if ($request->filled('record_type')) {
            $query->where('record_type', $request->record_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tournament_name')) {
            $query->where('tournament_name', 'like', '%' . trim((string) $request->tournament_name) . '%');
        }
        if ($request->filled('from')) {
            $query->where('awarded_on', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('awarded_on', '<=', $request->to);
        }

        $records = $query
            ->orderByRaw("CASE WHEN status = 'candidate' THEN 0 ELSE 1 END")
            ->orderByDesc('awarded_on')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('record_types.index', compact('records'));
    }

    public function create()
    {
        return view('record_types.create');
    }

    public function store(Request $request, AchievementRecordService $records)
    {
        $validated = $this->validateRecord($request, true);
        $bowler = ProBowler::query()
            ->where('license_no', strtoupper(trim((string) $validated['pro_bowler_license_no'])))
            ->first();

        if (! $bowler) {
            return back()
                ->withErrors(['pro_bowler_license_no' => '該当する選手が見つかりません'])
                ->withInput();
        }

        $requestedStatus = $validated['status'];
        unset($validated['pro_bowler_license_no'], $validated['status']);

        $record = RecordType::query()->create(array_merge($validated, [
            'pro_bowler_id' => $bowler->id,
            'gender' => ($validated['gender'] ?? null) ?: substr((string) $bowler->license_no, 0, 1),
            'status' => RecordType::STATUS_CANDIDATE,
            'source_type' => ($validated['source_type'] ?? null) ?: 'manual',
            'detected_at' => now(),
        ]));

        if ($requestedStatus === RecordType::STATUS_CONFIRMED) {
            $records->confirm($record, auth()->id());
        }

        return redirect()
            ->route('record_types.index')
            ->with('success', $requestedStatus === RecordType::STATUS_CONFIRMED
                ? '公認記録を登録しました。'
                : '公認記録候補を登録しました。');
    }

    public function show($id)
    {
        $recordType = RecordType::query()
            ->with(['proBowler', 'tournament', 'scoreSeriesDefinition'])
            ->findOrFail($id);

        return view('record_types.show', compact('recordType'));
    }

    public function edit($id)
    {
        $recordType = RecordType::query()
            ->with(['proBowler', 'tournament', 'scoreSeriesDefinition'])
            ->findOrFail($id);

        return view('record_types.edit', compact('recordType'));
    }

    public function update(Request $request, $id, AchievementRecordService $records)
    {
        $recordType = RecordType::query()->with('proBowler')->findOrFail($id);
        $validated = $this->validateRecord($request, false);
        $requestedStatus = $validated['status'];
        unset($validated['status'], $validated['pro_bowler_license_no']);

        $recordType->fill($validated);
        $records->syncSequenceAfterManualNumber($recordType);
        $recordType->save();

        try {
            if ($requestedStatus === RecordType::STATUS_CONFIRMED) {
                $recordType = $records->confirm($recordType, auth()->id());
            } elseif ($requestedStatus === RecordType::STATUS_REJECTED) {
                $recordType = $records->reject($recordType, $recordType->warning);
            } elseif ($recordType->status !== RecordType::STATUS_CONFIRMED) {
                $recordType->update(['status' => $requestedStatus]);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()])->withInput();
        }

        AwardCounter::syncToProBowler((int) $recordType->pro_bowler_id);

        return $this->redirectAfterMutation($request, (int) $recordType->pro_bowler_id)
            ->with('success', '公認記録を更新しました。');
    }

    public function destroy(Request $request, $id)
    {
        $recordType = RecordType::query()->findOrFail($id);
        $bowlerId = (int) $recordType->pro_bowler_id;
        $preservesTotal = $recordType->status === RecordType::STATUS_CONFIRMED
            || $recordType->count_applied_at !== null;
        $recordType->delete();

        return $this->redirectAfterMutation($request, $bowlerId)
            ->with(
                'success',
                $preservesTotal
                    ? '明細を削除しました。公認総数は履歴保護のため減らしていません。'
                    : '明細を削除しました。'
            );
    }

    private function redirectAfterMutation(Request $request, int $bowlerId)
    {
        if ($request->input('return_to') === 'pro_bowler_edit') {
            return redirect()->route('pro_bowlers.edit', $bowlerId);
        }

        return redirect()->route('record_types.index');
    }

    private function validateRecord(Request $request, bool $creating): array
    {
        $rules = [
            'record_type' => ['required', Rule::in(['perfect', 'seven_ten', 'eight_hundred'])],
            'tournament_name' => ['required', 'string', 'max:255'],
            'game_numbers' => ['nullable', 'string', 'max:255'],
            'frame_number' => ['nullable', 'string', 'max:50'],
            'awarded_on' => ['nullable', 'date'],
            'certification_number' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['M', 'F'])],
            'stage' => ['nullable', 'string', 'max:255'],
            'shift' => ['nullable', 'string', 'max:255'],
            'series_label' => ['nullable', 'string', 'max:255'],
            'series_start_game' => ['nullable', 'integer', 'min:1', 'max:99'],
            'series_end_game' => ['nullable', 'integer', 'min:1', 'max:99'],
            'series_total' => ['nullable', 'integer', 'min:0', 'max:900'],
            'registration_mode' => ['required', Rule::in([
                RecordType::MODE_HISTORICAL,
                RecordType::MODE_NEW,
            ])],
            'status' => ['required', Rule::in([
                RecordType::STATUS_CANDIDATE,
                RecordType::STATUS_CONFIRMED,
                RecordType::STATUS_REJECTED,
                RecordType::STATUS_VOID,
            ])],
            'source_type' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'evidence_text' => ['nullable', 'string'],
            'warning' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];

        if ($creating) {
            $rules['pro_bowler_license_no'] = ['required', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);
        if (
            ($validated['record_type'] ?? null) === 'eight_hundred'
            && (
                array_key_exists('series_start_game', $validated)
                || array_key_exists('series_end_game', $validated)
            )
            && (
                ! isset($validated['series_start_game'], $validated['series_end_game'])
                || (int) $validated['series_end_game']
                    - (int) $validated['series_start_game'] + 1 !== 3
            )
        ) {
            throw ValidationException::withMessages([
                'series_end_game' => '800シリーズの対象ゲームは正確に3ゲームで指定してください。',
            ]);
        }

        return $validated;
    }
}
