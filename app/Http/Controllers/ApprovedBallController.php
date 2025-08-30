<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApprovedBall;
use Illuminate\Support\Facades\Validator;

class ApprovedBallController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovedBall::query();

        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', $request->manufacturer);
        }

        if ($request->filled('name')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->name . '%')
                ->orWhere('name_kana', 'like', '%' . $request->name . '%');
            });
        }

        // 明示的に全部のカラムを取得
        $balls = $query->select([
            'id',
            'release_year',
            'manufacturer',
            'name',
            'name_kana',
            'approved'
        ])->orderBy('release_year', 'desc')
        ->paginate(20);

        $manufacturers = ApprovedBall::distinct()
            ->pluck('manufacturer')
            ->filter()
            ->unique();

        return view('approved_balls.index', compact('balls', 'manufacturers'));
    }

    public function create()
    {
        return view('approved_balls.create');
    }

    public function edit($id)
    {
        $ball = ApprovedBall::findOrFail($id);
        return view('approved_balls.edit', compact('ball'));
    }

    public function update(Request $request, $id)
    {
        $ball = ApprovedBall::findOrFail($id);

        $validated = $request->validate([
            'release_year' => 'nullable|digits:4|integer|min:1900|max:' . date('Y'),
            'manufacturer' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'name_kana' => 'nullable|string|max:255',
            'approved' => 'nullable|boolean',
        ]);

        $validated['approved'] = $request->has('approved'); // チェックされてたら true

        $ball->update($validated);

        return redirect()->route('approved_balls.index')->with('success', 'ボール情報を更新しました。');
    }

    public function showImportForm()
    {
        return view('approved_balls.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        // 最初の1行目（ヘッダー）を読み飛ばす
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            ApprovedBall::create([
                'name' => $row[0],
                'manufacturer' => $row[1],
                'release_year' => $row[2],
                // 必要なカラム追加
            ]);
        }

        fclose($handle);

        return redirect()->route('approved_balls.index')->with('success', 'CSVからインポート完了');
    }

    public function storeMultiple(Request $request)
    {
        $data = $request->input('balls', []);

        $validRows = [];

        foreach ($data as $index => $row) {
            // すべてのフィールドが空ならスキップ
            if (empty(array_filter($row))) {
                continue;
            }

            // バリデーション（ゆるめ）
            $validator = Validator::make($row, [
                'release_year' => 'nullable|digits:4|integer|min:1995|max:' . date('Y'),
                'manufacturer' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'name_kana' => 'nullable|string|max:255',
                'approved' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', "行 " . ($index + 1) . " の入力に不備があります。");
            }

            $validRows[] = $validator->validated();
        }

        foreach ($validRows as $row) {
            // チェックボックスは on/off または null のため boolean に補正
            $row['approved'] = !empty($row['approved']);
            ApprovedBall::create($row);
        }

        return redirect()->route('approved_balls.index')->with('success', 'ボールを登録しました。');
    }

    public function assignBallToPro(Request $request, ApprovedBall $ball)
    {
        $user = Auth::user();

        $year = $request->input('year', now()->year);

        DB::table('approved_ball_pro_bowler')->updateOrInsert(
            [
                'pro_bowler_license_no' => $user->pro_bowler_license_no,
                'approved_ball_id' => $ball->id,
                'year' => $year
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return back()->with('success', '使用ボールに登録されました');
    }
}
