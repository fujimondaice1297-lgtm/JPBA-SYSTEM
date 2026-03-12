<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Instructor;
use App\Models\ProBowler;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstructorController extends Controller
{
    private const GRADE_OPTIONS = [
        'C級',
        '準B級',
        'B級',
        '準A級',
        'A級',
        '2級',
        '1級',
    ];

    public function index(Request $request)
    {
        $query = Instructor::query()->with('district');
        $query = $this->applyFilters($query, $request);

        $instructors = $query
            ->orderBy('license_no')
            ->paginate(20)
            ->withQueryString();

        $districts = District::query()
            ->orderBy('id')
            ->get(['id', 'label']);

        return view('instructors.index', [
            'instructors' => $instructors,
            'districts'   => $districts,
            'grades'      => self::GRADE_OPTIONS,
        ]);
    }

    public function create()
    {
        $districts = District::query()
            ->orderBy('id')
            ->get(['id', 'label']);

        return view('instructors.create', compact('districts'));
    }

    public function store(Request $request)
    {
        $type = $request->input('instructor_type');

        $validated = $request->validate([
            'license_no'      => [
                'required',
                'string',
                Rule::unique('instructors', 'license_no')
                    ->where(fn ($q) => $q->where('instructor_type', $type)),
            ],
            'name'            => 'required|string',
            'name_kana'       => 'nullable|string',
            'sex'             => 'required|boolean',
            'district_id'     => 'nullable|integer|exists:districts,id',
            'instructor_type' => 'required|in:pro,certified',
            'grade'           => ['required', 'string', Rule::in(self::GRADE_OPTIONS)],
        ]);

        $instructor = new Instructor($validated);

        if ($validated['instructor_type'] === 'pro') {
            $pro = ProBowler::where('license_no', $validated['license_no'])->first();
            if ($pro) {
                $instructor->pro_bowler_id = $pro->id;
            }
        }

        $instructor->save();

        return redirect()
            ->route('instructors.index')
            ->with('success', 'インストラクターを登録しました。');
    }

    public function edit($licenseNo)
    {
        $instructor = Instructor::where('license_no', $licenseNo)->firstOrFail();

        $districts = District::query()
            ->orderBy('id')
            ->get(['id', 'label']);

        return view('instructors.edit', compact('instructor', 'districts'));
    }

    public function update(Request $request, $licenseNo)
    {
        $instructor = Instructor::where('license_no', $licenseNo)->firstOrFail();
        $type = $request->input('instructor_type', $instructor->instructor_type);

        $validated = $request->validate([
            'license_no'      => [
                'required',
                'string',
                Rule::unique('instructors', 'license_no')
                    ->where(fn ($q) => $q->where('instructor_type', $type))
                    ->ignore($instructor->license_no, 'license_no'),
            ],
            'name'            => 'required|string',
            'name_kana'       => 'nullable|string',
            'sex'             => 'required|boolean',
            'district_id'     => 'nullable|integer|exists:districts,id',
            'instructor_type' => 'required|in:pro,certified',
            'grade'           => ['required', 'string', Rule::in(self::GRADE_OPTIONS)],
        ]);

        $instructor->fill($validated);

        if ($validated['instructor_type'] === 'pro') {
            $pro = ProBowler::where('license_no', $validated['license_no'])->first();
            $instructor->pro_bowler_id = $pro?->id;
        } else {
            $instructor->pro_bowler_id = null;
        }

        $instructor->save();

        return redirect()
            ->route('instructors.index')
            ->with('success', 'インストラクターを更新しました。');
    }

    public function exportPdf(Request $request)
    {
        $query = Instructor::query()->with('district');
        $query = $this->applyFilters($query, $request);

        $instructors = $query
            ->orderBy('license_no')
            ->get();

        $options = new Options();
        $options->set('defaultFont', 'ipaexg');

        $dompdf = new Dompdf($options);
        $html = view('instructors.pdf', compact('instructors'))->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="instructors.pdf"');
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . trim((string) $request->input('name')) . '%');
        }

        if ($request->filled('license_no')) {
            $query->where('license_no', trim((string) $request->input('license_no')));
        }

        $districtId = $request->input('district_id');
        if ($districtId !== null && $districtId !== '' && ctype_digit((string) $districtId)) {
            $query->where('district_id', (int) $districtId);
        } elseif ($request->filled('district')) {
            $districtLabel = trim((string) $request->input('district'));
            $query->whereHas('district', function ($q) use ($districtLabel) {
                $q->where('label', $districtLabel);
            });
        }

        $sex = $request->input('sex');
        if ($sex !== null && $sex !== '' && in_array((string) $sex, ['0', '1'], true)) {
            $query->where('sex', (int) $sex);
        } elseif ($request->filled('gender')) {
            $gender = trim((string) $request->input('gender'));
            if ($gender === '男性') {
                $query->where('sex', 1);
            } elseif ($gender === '女性') {
                $query->where('sex', 0);
            }
        }

        if ($request->filled('instructor_class')) {
            switch ($request->input('instructor_class')) {
                case 'pro_bowler':
                    $query->proBowler();
                    break;
                case 'pro_instructor':
                    $query->proInstructor();
                    break;
                case 'certified_instructor':
                    $query->certifiedInstructor();
                    break;
            }
        }

        if ($request->filled('grade')) {
            $query->where('grade', trim((string) $request->input('grade')));
        }

        return $query;
    }
}