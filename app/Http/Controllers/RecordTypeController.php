<?php

namespace App\Http\Controllers;

use App\Models\RecordType;
use App\Models\ProBowler;
use Illuminate\Http\Request;
use App\Services\AwardCounter; // ★追加

class RecordTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = RecordType::with('proBowler');

        if ($request->filled('player_identifier')) {
            $query->whereHas('proBowler', function ($q) use ($request) {
                $q->where('name_kanji', 'like', '%' . $request->player_identifier . '%')
                  ->orWhere('name_kana', 'like', '%' . $request->player_identifier . '%')
                  ->orWhere('license_no', 'like', '%' . $request->player_identifier . '%');
            });
        }

        if ($request->filled('record_type')) {
            $query->where('record_type', $request->record_type);
        }

        if ($request->filled('from')) {
            $query->where('awarded_on', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('awarded_on', '<=', $request->to);
        }

        $records = $query->orderBy('awarded_on', 'desc')->paginate(20);
        return view('record_types.index', compact('records'));
    }

    public function create()
    {
        $proBowlers = ProBowler::all()->map(function ($bowler) {
            $displayName = $bowler->name_kanji ?? $bowler->name_kana ?? ('ID:' . $bowler->id);
            $bowler->display_name = "{$displayName}（{$bowler->license_no}）";
            return $bowler;
        });

        return view('record_types.create', compact('proBowlers'));
    }

    public function store(Request $request)
    {
        $data = $request->all();

        // ライセンス番号でプロを検索
        $bowler = ProBowler::where('license_no', $data['pro_bowler_license_no'])->first();

        if (!$bowler) {
            return back()->withErrors(['pro_bowler_license_no' => '該当する選手が見つかりません'])->withInput();
        }

        $validated = $request->validate([
            'record_type' => 'required|in:perfect,seven_ten,eight_hundred',
            'tournament_name' => 'required|string|max:255',
            'game_numbers' => 'required|string|max:255',
            'frame_number' => 'nullable|string|max:50',
            'awarded_on' => 'required|date',
            'certification_number' => 'required|string|max:100',
            'pro_bowler_license_no' => 'required|string'
        ]);

        // 保存
        RecordType::create([
            'record_type' => $validated['record_type'],
            'pro_bowler_id' => $bowler->id,
            'tournament_name' => $validated['tournament_name'],
            'game_numbers' => $validated['game_numbers'],
            'frame_number' => $validated['frame_number'] ?? null,
            'awarded_on' => $validated['awarded_on'],
            'certification_number' => $validated['certification_number'],
        ]);

        // ★達成数を即同期
        AwardCounter::syncToProBowler($bowler->id);

        return redirect()->route('record_types.index')->with('success', '記録を登録しました。');
    }

    public function show($id)
    {
        $recordType = RecordType::with('proBowler')->findOrFail($id);
        return view('record_types.show', compact('recordType'));
    }

    public function edit($id)
    {
        $recordType = RecordType::findOrFail($id);

        $proBowlers = ProBowler::all()->map(function ($bowler) {
            $displayName = $bowler->name_kanji ?? $bowler->name_kana ?? ('ID:' . $bowler->id);
            $bowler->display_name = "{$displayName}（{$bowler->license_no}）";
            return $bowler;
        });

        return view('record_types.edit', compact('recordType', 'proBowlers'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'record_type' => 'required|string|in:perfect,seven_ten,eight_hundred',
            'pro_bowler_id' => 'required|exists:pro_bowlers,id',
            'tournament_name' => 'required|string',
            'game_numbers' => 'required|string',
            'frame_number' => 'nullable|string',
            'awarded_on' => 'required|date',
            'certification_number' => 'required|string',
        ]);

        $recordType = RecordType::findOrFail($id);
        $oldBowlerId = (int)$recordType->pro_bowler_id;

        $recordType->update($validated);

        // ★選手が変わっていたら両方同期、同じなら一人分同期
        $newBowlerId = (int)$recordType->pro_bowler_id;
        AwardCounter::syncToProBowler($newBowlerId);
        if ($oldBowlerId !== $newBowlerId) {
            AwardCounter::syncToProBowler($oldBowlerId);
        }

        return redirect()->route('record_types.index')->with('success', '記録を更新しました');
    }

    public function destroy($id)
    {
        $recordType = RecordType::findOrFail($id);
        $bowlerId = (int)$recordType->pro_bowler_id;
        $recordType->delete();

        // ★削除後も同期
        AwardCounter::syncToProBowler($bowlerId);

        return redirect()->route('record_types.index')->with('success', '記録を削除しました。');
    }

    private function validateRecord(Request $request)
    {
        return $request->validate([
            'record_type' => 'required|string|in:perfect,seven_ten,eight_hundred',
            'pro_bowler_id' => 'required|exists:pro_bowlers,id',
            'tournament_name' => 'required|string|max:255',
            'game_numbers' => 'required|string|max:255',
            'frame_number' => 'nullable|string|max:50',
            'awarded_on' => 'required|date',
            'certification_number' => 'required|string|max:100',
        ]);
    }
}
