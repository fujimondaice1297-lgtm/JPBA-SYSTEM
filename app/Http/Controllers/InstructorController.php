<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\InstructorRegistry;
use App\Models\ProBowler;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        return view('instructors.create', [
            'districts' => $districts,
            'grades'    => self::GRADE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRegistryInput($request);

        $registry = new InstructorRegistry(
            array_merge(
                [
                    'source_type' => 'manual',
                    'source_key'  => (string) Str::uuid(),
                ],
                $this->buildRegistryPayload($validated)
            )
        );

        $registry->save();

        return redirect()
            ->route('instructors.index')
            ->with('success', 'インストラクターを登録しました。');
    }

    public function edit(string $instructorKey)
    {
        $instructor = $this->findEditableRegistry($instructorKey);

        $districts = District::query()
            ->orderBy('id')
            ->get(['id', 'label']);

        return view('instructors.edit', [
            'instructor' => $instructor,
            'districts'  => $districts,
            'grades'     => self::GRADE_OPTIONS,
        ]);
    }

    public function update(Request $request, string $instructorKey)
    {
        $instructor = $this->findEditableRegistry($instructorKey);
        $validated = $this->validateRegistryInput($request, $instructor);

        $instructor->fill($this->buildRegistryPayload($validated, $instructor));
        $instructor->save();

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

    private function findEditableRegistry(string $instructorKey): InstructorRegistry
    {
        if (ctype_digit($instructorKey)) {
            $byId = InstructorRegistry::query()->find((int) $instructorKey);
            if ($byId) {
                return $byId;
            }
        }

        return InstructorRegistry::query()
            ->where('source_key', $instructorKey)
            ->orWhere('legacy_instructor_license_no', $instructorKey)
            ->orWhere('license_no', $instructorKey)
            ->orWhere('cert_no', $instructorKey)
            ->firstOrFail();
    }

    private function validateRegistryInput(Request $request, ?InstructorRegistry $existing = null): array
    {
        $type = (string) $request->input('instructor_type');
        $ignoreId = $existing?->id;

        $rules = [
            'name'                => ['required', 'string', 'max:255'],
            'name_kana'           => ['nullable', 'string', 'max:255'],
            'sex'                 => ['required', 'boolean'],
            'district_id'         => ['nullable', 'integer', 'exists:districts,id'],
            'instructor_type'     => ['required', 'in:pro,certified'],
            'grade'               => ['required', 'string', Rule::in(self::GRADE_OPTIONS)],
            'is_active'           => ['nullable', 'boolean'],
            'is_visible'          => ['nullable', 'boolean'],
            'coach_qualification' => ['nullable', 'boolean'],
        ];

        if ($type === 'pro') {
            $rules['license_no'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('instructor_registry', 'license_no')->ignore($ignoreId),
            ];
            $rules['cert_no'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['cert_no'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('instructor_registry', 'cert_no')->ignore($ignoreId),
            ];
            $rules['license_no'] = ['nullable', 'string', 'max:255'];
        }

        return $request->validate($rules);
    }

    private function buildRegistryPayload(array $validated, ?InstructorRegistry $existing = null): array
    {
        $isManual = $existing ? ($existing->source_type === 'manual') : true;

        if ($existing && !$isManual) {
            $licenseNo = $existing->license_no;
            $certNo = $existing->cert_no;
            $proBowlerId = $existing->pro_bowler_id;
            $category = $existing->instructor_category;
        } else {
            $type = (string) $validated['instructor_type'];

            $licenseNo = $type === 'pro'
                ? $this->normalizeNullableString($validated['license_no'] ?? null)
                : null;

            $certNo = $type === 'certified'
                ? $this->normalizeNullableString($validated['cert_no'] ?? null)
                : null;

            $matchedBowler = $licenseNo
                ? ProBowler::query()->where('license_no', $licenseNo)->first()
                : null;

            $proBowlerId = $matchedBowler?->id;
            $category = $type === 'certified'
                ? 'certified'
                : ($matchedBowler ? 'pro_bowler' : 'pro_instructor');
        }

        return [
            'legacy_instructor_license_no' => $existing?->legacy_instructor_license_no,
            'pro_bowler_id'                => $proBowlerId,
            'license_no'                   => $licenseNo,
            'cert_no'                      => $certNo,
            'name'                         => trim((string) $validated['name']),
            'name_kana'                    => $this->normalizeNullableString($validated['name_kana'] ?? null),
            'sex'                          => ((int) $validated['sex']) === 1,
            'district_id'                  => $validated['district_id'] !== null && $validated['district_id'] !== ''
                ? (int) $validated['district_id']
                : null,
            'instructor_category'          => $category,
            'grade'                        => $validated['grade'],
            'coach_qualification'          => (bool) ($validated['coach_qualification'] ?? false),
            'is_active'                    => (bool) ($validated['is_active'] ?? true),
            'is_visible'                   => (bool) ($validated['is_visible'] ?? true),
            'last_synced_at'               => now(),
            'notes'                        => $existing?->notes ?: 'manual instructor entry',
        ];
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}