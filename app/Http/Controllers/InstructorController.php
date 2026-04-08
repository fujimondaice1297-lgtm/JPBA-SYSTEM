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

    private const RENEWAL_STATUS_OPTIONS = [
        'pending' => '未更新',
        'renewed' => '更新済み',
        'expired' => '期限切れ',
    ];

    public function index(Request $request)
    {
        $query = InstructorRegistry::query()->with(['district', 'proBowler']);

        $includeHistory = $request->boolean('include_history')
            || $request->input('instructor_class') === 'retired'
            || $request->input('renewal_status') === 'expired';

        if (!$includeHistory) {
            $query->where('is_current', true);
        }

        $query = $this->applyFilters($query, $request);

        $instructors = $this->applyDefaultOrder($query)
            ->paginate(20)
            ->withQueryString();

        $districts = District::query()
            ->orderBy('id')
            ->get(['id', 'label']);

        return view('instructors.index', [
            'instructors'      => $instructors,
            'districts'        => $districts,
            'grades'           => self::GRADE_OPTIONS,
            'renewalStatuses'  => self::RENEWAL_STATUS_OPTIONS,
        ]);
    }

    public function create()
    {
        $districts = District::query()
            ->orderBy('id')
            ->get(['id', 'label']);

        return view('instructors.create', [
            'districts'       => $districts,
            'grades'          => self::GRADE_OPTIONS,
            'renewalStatuses' => self::RENEWAL_STATUS_OPTIONS,
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
        $this->applyCurrentTransitions($registry);

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
            'instructor'        => $instructor,
            'districts'         => $districts,
            'grades'            => self::GRADE_OPTIONS,
            'renewalStatuses'   => self::RENEWAL_STATUS_OPTIONS,
            'linkableProBowlers'=> $this->buildLinkableProBowlerCandidates($instructor),
        ]);
    }

    public function update(Request $request, string $instructorKey)
    {
        $instructor = $this->findEditableRegistry($instructorKey);
        $validated = $this->validateRegistryInput($request, $instructor);

        $instructor->fill($this->buildRegistryPayload($validated, $instructor));
        $instructor->save();

        if ($this->canManuallyLinkSyncedCertified($instructor)) {
            $this->applySyncedCertifiedLinkState($instructor);
        } else {
            $this->applyCurrentTransitions($instructor);
        }

        return redirect()
            ->route('instructors.index')
            ->with('success', 'インストラクターを更新しました。');
    }

    public function exportPdf(Request $request)
    {
        $query = InstructorRegistry::query()->with(['district', 'proBowler']);

        $includeHistory = $request->boolean('include_history')
            || $request->input('instructor_class') === 'retired'
            || $request->input('renewal_status') === 'expired';

        if (!$includeHistory) {
            $query->where('is_current', true);
        }

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
                case 'certified':
                case 'certified_instructor':
                    $query->certifiedInstructor();
                    break;
                case 'retired':
                    $query->retired();
                    break;
            }
        }

        if ($request->filled('grade')) {
            $query->where('grade', trim((string) $request->input('grade')));
        }

        if ($request->filled('renewal_year') && ctype_digit((string) $request->input('renewal_year'))) {
            $query->where('renewal_year', (int) $request->input('renewal_year'));
        }

        if ($request->filled('renewal_status')) {
            $renewalStatus = trim((string) $request->input('renewal_status'));
            if (array_key_exists($renewalStatus, self::RENEWAL_STATUS_OPTIONS)) {
                $query->where('renewal_status', $renewalStatus);
            }
        }

        if ($request->boolean('unlinked_certified')) {
            $query->where('source_type', 'auth_instructor_csv')
                ->where('instructor_category', 'certified')
                ->whereNull('pro_bowler_id');
        }

        return $query;
    }

    private function applyDefaultOrder(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_current')
            ->orderByRaw('renewal_year desc nulls last')
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
        $rawType = (string) $request->input('instructor_type');
        $type = in_array($rawType, ['pro', 'pro_instructor'], true) ? 'pro_instructor' : $rawType;
        $ignoreId = $existing?->id;

        $rules = [
            'name'                => ['required', 'string', 'max:255'],
            'name_kana'           => ['nullable', 'string', 'max:255'],
            'sex'                 => ['required', 'boolean'],
            'district_id'         => ['nullable', 'integer', 'exists:districts,id'],
            'instructor_type'     => ['required', 'in:pro,pro_instructor,certified'],
            'grade'               => ['nullable', 'string', Rule::in(self::GRADE_OPTIONS)],
            'is_active'           => ['nullable', 'boolean'],
            'is_visible'          => ['nullable', 'boolean'],
            'coach_qualification' => ['nullable', 'boolean'],
            'renewal_year'        => ['nullable', 'integer', 'min:2000', 'max:2099'],
            'renewal_due_on'      => ['nullable', 'date'],
            'renewal_status'      => ['nullable', 'string', Rule::in(array_keys(self::RENEWAL_STATUS_OPTIONS))],
            'renewed_at'          => ['nullable', 'date'],
            'renewal_note'        => ['nullable', 'string', 'max:2000'],
            'linked_pro_bowler_id'=> ['nullable', 'integer', 'exists:pro_bowlers,id'],
        ];

        if ($type === 'pro_instructor') {
            $rules['license_no'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('instructor_registry', 'license_no')
                    ->where(fn ($query) => $query->where('is_current', true))
                    ->ignore($ignoreId),
            ];
            $rules['cert_no'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['cert_no'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('instructor_registry', 'cert_no')
                    ->where(fn ($query) => $query->where('is_current', true))
                    ->ignore($ignoreId),
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
            $sourceRegisteredAt = $existing->source_registered_at;
            $isCurrent = $existing->is_current;
            $supersededAt = $existing->superseded_at;
            $supersedeReason = $existing->supersede_reason;

            if ($this->canManuallyLinkSyncedCertified($existing) && array_key_exists('linked_pro_bowler_id', $validated)) {
                $linkedProBowlerId = $validated['linked_pro_bowler_id'];

                if ($linkedProBowlerId === null) {
                    $proBowlerId = null;
                } else {
                    $linkedBowler = ProBowler::query()->find((int) $linkedProBowlerId);
                    $proBowlerId = $linkedBowler?->id;

                    if ($licenseNo === null && $linkedBowler) {
                        $licenseNo = $linkedBowler->license_no;
                    }
                }
            }
        } else {
            $rawType = (string) $validated['instructor_type'];
            $type = in_array($rawType, ['pro', 'pro_instructor'], true) ? 'pro_instructor' : $rawType;

            $licenseNo = $type === 'pro_instructor'
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

            $sourceRegisteredAt = $existing?->source_registered_at;
            $isCurrent = true;
            $supersededAt = null;
            $supersedeReason = null;
        }

        $renewalYear = $validated['renewal_year'] ?? $existing?->renewal_year;
        $renewalDueOn = $validated['renewal_due_on'] ?? ($renewalYear ? sprintf('%04d-12-31', (int) $renewalYear) : $existing?->renewal_due_on?->format('Y-m-d'));
        $renewalStatus = $validated['renewal_status'] ?? $existing?->renewal_status;
        $renewedAt = $validated['renewed_at'] ?? $existing?->renewed_at?->format('Y-m-d');

        if ($renewalStatus === 'renewed' && empty($renewedAt)) {
            $renewedAt = now()->toDateString();
        }
        if ($renewalStatus !== 'renewed') {
            $renewedAt = null;
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
            'grade'                        => $validated['grade'] ?? null,
            'coach_qualification'          => (bool) ($validated['coach_qualification'] ?? false),
            'is_active'                    => (bool) ($validated['is_active'] ?? true),
            'is_visible'                   => (bool) ($validated['is_visible'] ?? true),
            'source_registered_at'         => $sourceRegisteredAt,
            'is_current'                   => $isCurrent,
            'superseded_at'                => $supersededAt,
            'supersede_reason'             => $supersedeReason,
            'renewal_year'                 => $renewalYear !== null && $renewalYear !== '' ? (int) $renewalYear : null,
            'renewal_due_on'               => $renewalDueOn,
            'renewal_status'               => $renewalStatus,
            'renewed_at'                   => $renewedAt,
            'renewal_note'                 => $this->normalizeNullableString($validated['renewal_note'] ?? null),
            'last_synced_at'               => now(),
            'notes'                        => $existing?->notes ?: 'manual instructor entry',
        ];
    }

    private function applyCurrentTransitions(InstructorRegistry $registry): void
    {
        if (!$registry->is_current) {
            return;
        }

        $rows = $this->relatedCurrentRowsForRegistry($registry)->get();
        if ($rows->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($rows as $row) {
            $row->is_current = false;
            $row->is_active = false;
            $row->superseded_at = $now;
            $row->supersede_reason = $this->resolveTransitionReason(
                $row->instructor_category,
                $registry->instructor_category
            );
            $row->last_synced_at = $now;
            $row->save();
        }
    }

    private function applySyncedCertifiedLinkState(InstructorRegistry $registry): void
    {
        $currentProTarget = $this->findCurrentProTargetForRegistry($registry);

        if ($currentProTarget) {
            $registry->is_current = false;
            $registry->is_active = false;
            $registry->superseded_at = now();
            $registry->supersede_reason = $this->resolveTransitionReason(
                'certified',
                $currentProTarget->instructor_category
            );
            $registry->last_synced_at = now();
            $registry->save();

            return;
        }

        $this->applyCurrentTransitions($registry);
    }

    private function relatedCurrentRowsForRegistry(InstructorRegistry $registry): Builder
    {
        return InstructorRegistry::query()
            ->where('id', '!=', $registry->id)
            ->where('is_current', true)
            ->where(function ($query) use ($registry) {
                $hasCondition = false;

                if ($registry->pro_bowler_id !== null) {
                    $query->orWhere('pro_bowler_id', $registry->pro_bowler_id);
                    $hasCondition = true;
                }

                if ($registry->license_no !== null) {
                    $query->orWhere('license_no', $registry->license_no)
                        ->orWhere('legacy_instructor_license_no', $registry->license_no);
                    $hasCondition = true;
                }

                if ($registry->legacy_instructor_license_no !== null) {
                    $query->orWhere('license_no', $registry->legacy_instructor_license_no)
                        ->orWhere('legacy_instructor_license_no', $registry->legacy_instructor_license_no);
                    $hasCondition = true;
                }

                if ($registry->cert_no !== null) {
                    $query->orWhere('cert_no', $registry->cert_no);
                    $hasCondition = true;
                }

                if (!$hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    private function findCurrentProTargetForRegistry(InstructorRegistry $registry): ?InstructorRegistry
    {
        return InstructorRegistry::query()
            ->whereIn('instructor_category', ['pro_bowler', 'pro_instructor'])
            ->where('is_current', true)
            ->where('is_active', true)
            ->where(function ($query) use ($registry) {
                $hasCondition = false;

                if ($registry->pro_bowler_id !== null) {
                    $query->orWhere('pro_bowler_id', $registry->pro_bowler_id);
                    $hasCondition = true;
                }

                if ($registry->license_no !== null) {
                    $query->orWhere('license_no', $registry->license_no)
                        ->orWhere('legacy_instructor_license_no', $registry->license_no);
                    $hasCondition = true;
                }

                if (!$hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('last_synced_at')
            ->orderBy('id')
            ->first();
    }

    private function buildLinkableProBowlerCandidates(InstructorRegistry $registry)
    {
        if (!$this->canManuallyLinkSyncedCertified($registry)) {
            return collect();
        }

        $candidates = collect();

        if ($registry->pro_bowler_id !== null) {
            $current = ProBowler::query()
                ->with('district')
                ->find($registry->pro_bowler_id);

            if ($current) {
                $candidates->push($current);
            }
        }

        $query = ProBowler::query()->with('district');

        $query->where(function ($q) use ($registry) {
            $hasCondition = false;

            if ($registry->license_no !== null && $registry->license_no !== '') {
                $q->orWhere('license_no', $registry->license_no);
                $hasCondition = true;
            }

            if ($registry->name !== null && $registry->name !== '') {
                $q->orWhere('name_kanji', $registry->name);
                $hasCondition = true;
            }

            if ($registry->name_kana !== null && $registry->name_kana !== '') {
                $q->orWhere('name_kana', $registry->name_kana);
                $hasCondition = true;
            }

            if (!$hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });

        $rows = $query
            ->orderBy('license_no')
            ->limit(20)
            ->get();

        $candidates = $candidates
            ->concat($rows)
            ->unique('id')
            ->sortBy([
                fn ($row) => ($registry->license_no !== null && $row->license_no === $registry->license_no) ? 0 : 1,
                fn ($row) => ($registry->name !== null && $row->name_kanji === $registry->name) ? 0 : 1,
                fn ($row) => ($registry->name_kana !== null && $row->name_kana === $registry->name_kana) ? 0 : 1,
                fn ($row) => ($registry->district_id !== null && $row->district_id === $registry->district_id) ? 0 : 1,
                'license_no',
            ])
            ->values();

        return $candidates;
    }

    private function canManuallyLinkSyncedCertified(InstructorRegistry $registry): bool
    {
        return $registry->source_type === 'auth_instructor_csv'
            && $registry->instructor_category === 'certified';
    }

    private function resolveTransitionReason(string $fromCategory, string $toCategory): string
    {
        return match (true) {
            $fromCategory === 'certified' && $toCategory === 'pro_instructor' => 'promoted_to_pro_instructor',
            $fromCategory === 'certified' && $toCategory === 'pro_bowler'     => 'promoted_to_pro_bowler',
            $fromCategory === 'pro_instructor' && $toCategory === 'pro_bowler' => 'promoted_to_pro_bowler',
            $fromCategory === 'pro_bowler' && $toCategory === 'certified'     => 'downgraded_to_certified',
            $fromCategory === 'pro_instructor' && $toCategory === 'certified' => 'downgraded_to_certified',
            default                                                           => 'replaced_by_manual_entry',
        };
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