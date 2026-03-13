<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Instructor;
use App\Models\InstructorRegistry;
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
        $query = InstructorRegistry::query()->with(['district', 'proBowler']);
        $query = $this->applyFilters($query, $request);

        $instructors = $this->applyDefaultOrder($query)
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
        $this->syncInstructorRegistryFromLegacy($instructor);

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
        $this->syncInstructorRegistryFromLegacy($instructor);

        return redirect()
            ->route('instructors.index')
            ->with('success', 'インストラクターを更新しました。');
    }

    public function exportPdf(Request $request)
    {
        $query = InstructorRegistry::query()->with(['district', 'proBowler']);
        $query = $this->applyFilters($query, $request);

        $instructors = $this->applyDefaultOrder($query)->get();

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
            $keyword = trim((string) $request->input('license_no'));
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';

                $q->where('license_no', 'like', $like)
                    ->orWhere('legacy_instructor_license_no', 'like', $like)
                    ->orWhere('cert_no', 'like', $like);
            });
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
            $query->where('sex', ((int) $sex) === 1);
        } elseif ($request->filled('gender')) {
            $gender = trim((string) $request->input('gender'));
            if ($gender === '男性') {
                $query->where('sex', true);
            } elseif ($gender === '女性') {
                $query->where('sex', false);
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

    private function applyDefaultOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw("coalesce(license_no, cert_no, legacy_instructor_license_no, '') asc")
            ->orderBy('name')
            ->orderBy('id');
    }

    private function syncInstructorRegistryFromLegacy(Instructor $instructor): void
    {
        $registry = InstructorRegistry::query()
            ->where('source_type', 'legacy_instructors')
            ->where('source_key', $instructor->license_no)
            ->first();

        $payload = [
            'legacy_instructor_license_no' => $instructor->license_no,
            'pro_bowler_id'                => $instructor->pro_bowler_id,
            'license_no'                   => $instructor->license_no,
            'cert_no'                      => $registry?->cert_no,
            'name'                         => $instructor->name,
            'name_kana'                    => $instructor->name_kana,
            'sex'                          => $instructor->sex,
            'district_id'                  => $instructor->district_id,
            'instructor_category'          => $this->resolveRegistryCategoryFromLegacy($instructor),
            'grade'                        => $instructor->grade,
            'coach_qualification'          => (bool) $instructor->coach_qualification,
            'is_active'                    => (bool) $instructor->is_active,
            'is_visible'                   => (bool) $instructor->is_visible,
            'last_synced_at'               => now(),
            'notes'                        => $registry?->notes ?: 'synced from legacy instructors form',
        ];

        if ($registry) {
            $registry->fill($payload)->save();
            return;
        }

        InstructorRegistry::create(array_merge([
            'source_type' => 'legacy_instructors',
            'source_key'  => $instructor->license_no,
        ], $payload));
    }

    private function resolveRegistryCategoryFromLegacy(Instructor $instructor): string
    {
        if ($instructor->instructor_type === 'certified') {
            return 'certified';
        }

        if (!empty($instructor->pro_bowler_id)) {
            return 'pro_bowler';
        }

        return 'pro_instructor';
    }
}