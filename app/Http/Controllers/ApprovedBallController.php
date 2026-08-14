<?php

namespace App\Http\Controllers;

use App\Models\ApprovedBall;
use App\Models\BallManufacturer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ApprovedBallController extends Controller
{
    public function image(ApprovedBall $approvedBall)
    {
        $path = $approvedBall->image_path;
        abort_unless(
            $path && Storage::disk('public')->exists($path),
            404
        );

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=604800',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index(Request $request)
    {
        $query = ApprovedBall::query()->with('catalogManufacturer');

        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', $request->string('manufacturer')->toString());
        }
        if ($request->filled('brand')) {
            $query->where('brand', $request->string('brand')->toString());
        }
        if ($request->filled('catalog_status')) {
            $query->where(
                'catalog_status',
                $request->string('catalog_status')->toString()
            );
        }
        if ($request->filled('name')) {
            $keyword = $request->string('name')->toString();
            $query->where(function ($subQuery) use ($keyword): void {
                $subQuery
                    ->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('name_kana', 'like', '%'.$keyword.'%')
                    ->orWhere('brand', 'like', '%'.$keyword.'%');
            });
        }

        $balls = $query
            ->orderBy('manufacturer')
            ->orderBy('brand')
            ->orderByRaw('COALESCE(sort_name, name)')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $manufacturers = ApprovedBall::query()
            ->whereNotNull('manufacturer')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer');
        $brandsQuery = ApprovedBall::query()->whereNotNull('brand');
        if ($request->filled('manufacturer')) {
            $brandsQuery->where(
                'manufacturer',
                $request->string('manufacturer')->toString()
            );
        }
        $brands = $brandsQuery
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');
        $catalogSummary = BallManufacturer::query()
            ->withCount([
                'approvedBalls',
                'approvedBalls as image_count' => fn ($summaryQuery) => $summaryQuery
                    ->whereNotNull('image_path'),
            ])
            ->orderBy('sort_order')
            ->get();

        return view('approved_balls.index', compact(
            'balls',
            'manufacturers',
            'brands',
            'catalogSummary'
        ));
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
            'release_date' => 'nullable|date',
            'manufacturer' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'name_kana' => 'nullable|string|max:255',
            'catalog_status' => 'required|in:listed,archive,manual,hidden',
            'approved' => 'nullable|boolean',
        ]);
        $validated['approved'] = $request->boolean('approved');
        $validated['sort_name'] = app(\App\Services\BallCatalogScraperService::class)
            ->sortName($validated['name_kana'] ?? null, $validated['name']);
        $ball->update($validated);

        return redirect()
            ->route('approved_balls.index')
            ->with('success', 'ボール情報を更新しました。');
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
        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            ApprovedBall::create([
                'name' => $row[0],
                'manufacturer' => $row[1],
                'release_date' => ! empty($row[2]) ? $row[2].'-01-01' : null,
                'catalog_status' => 'manual',
            ]);
        }
        fclose($handle);

        return redirect()
            ->route('approved_balls.index')
            ->with('success', 'CSVからインポートしました。');
    }

    public function storeMultiple(Request $request)
    {
        $validRows = [];
        foreach ($request->input('balls', []) as $index => $row) {
            if (empty(array_filter($row))) {
                continue;
            }
            $validator = Validator::make($row, [
                'release_year' => 'nullable|digits:4|integer|min:1995|max:'.date('Y'),
                'manufacturer' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'name_kana' => 'nullable|string|max:255',
                'approved' => 'nullable|boolean',
            ]);
            if ($validator->fails()) {
                return redirect()
                    ->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', '行 '.($index + 1).' の入力に不備があります。');
            }
            $validRows[] = $validator->validated();
        }

        foreach ($validRows as $row) {
            $releaseYear = $row['release_year'] ?? null;
            unset($row['release_year']);
            $row['release_date'] = $releaseYear ? $releaseYear.'-01-01' : null;
            $row['approved'] = ! empty($row['approved']);
            $row['catalog_status'] = 'manual';
            $row['sort_name'] = app(\App\Services\BallCatalogScraperService::class)
                ->sortName($row['name_kana'] ?? null, $row['name']);
            ApprovedBall::create($row);
        }

        return redirect()
            ->route('approved_balls.index')
            ->with('success', 'ボールを登録しました。');
    }

    public function assignBallToPro(Request $request, ApprovedBall $ball)
    {
        $user = Auth::user();
        $year = $request->integer('year', (int) now()->year);

        DB::table('approved_ball_pro_bowler')->updateOrInsert(
            [
                'pro_bowler_license_no' => $user->pro_bowler_license_no,
                'approved_ball_id' => $ball->id,
                'year' => $year,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return back()->with('success', '使用ボールに登録されました。');
    }
}
