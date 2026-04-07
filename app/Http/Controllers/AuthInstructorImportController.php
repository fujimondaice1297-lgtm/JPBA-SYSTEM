<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\InstructorRegistry;
use App\Models\ProBowler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthInstructorImportController extends Controller
{
    public function form()
    {
        return view('instructors.import_auth');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $fh = fopen($request->file('csv')->getRealPath(), 'r');
        if (!$fh) {
            return back()->withErrors(['csv' => 'CSVを開けません。']);
        }

        @stream_filter_append($fh, 'convert.iconv.CP932/UTF-8//IGNORE');

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return back()->withErrors(['csv' => 'ヘッダー行を読めません。']);
        }

        $normalizeHeader = function ($value): string {
            $s = (string) $value;
            $s = preg_replace('/^\x{FEFF}/u', '', $s);
            $s = str_replace(["\r", "\n", "\t"], '', $s);
            $s = preg_replace('/[\x{00A0}\x{3000}\s]+/u', '', $s);
            $s = mb_convert_kana($s, 'asKV', 'UTF-8');
            $s = mb_strtolower($s, 'UTF-8');

            return $s;
        };

        $normalizeCompact = function ($value): string {
            $s = trim((string) $value);
            if ($s === '') {
                return '';
            }

            $s = str_replace(["\r", "\n", "\t"], '', $s);
            $s = preg_replace('/[\x{00A0}\x{3000}\s]+/u', '', $s);
            $s = mb_convert_kana($s, 'asKV', 'UTF-8');

            return $s;
        };

        $normalizeDistrictKey = function ($value) use ($normalizeCompact): string {
            $s = $normalizeCompact($value);

            return str_replace(['・', '･', '·', '•', '‧'], '', $s);
        };

        $headerMap = [];
        foreach ($header as $i => $name) {
            $key = $normalizeHeader($name);
            if ($key !== '' && !array_key_exists($key, $headerMap)) {
                $headerMap[$key] = $i;
            }
        }

        $col = function (array $aliases) use ($headerMap, $normalizeHeader) {
            foreach ($aliases as $alias) {
                $key = $normalizeHeader($alias);
                if (array_key_exists($key, $headerMap)) {
                    return $headerMap[$key];
                }
            }

            return null;
        };

        $C = (object) [
            'id'       => $col(['#ID', 'ID']),
            'license'  => $col(['ライセンスNo', 'ライセンスNO', 'ライセンスＮｏ']),
            'grade'    => $col(['認定級']),
            'name'     => $col(['名前', '氏名']),
            'kana'     => $col(['名前（フリガナ）', 'フリガナ']),
            'sex'      => $col(['性別']),
            'district' => $col(['地区']),
            'visible'  => $col(['表示フラグ']),
            'active'   => $col(['有効フラグ']),
            'coach'    => $col(['コーチ資格']),
        ];

        if ($C->id === null || $C->grade === null || $C->name === null) {
            fclose($fh);

            return back()->withErrors(['csv' => '必要な列（#ID / 認定級 / 名前）を見つけられません。']);
        }

        $districtModels = District::query()->get(['id', 'name', 'label']);
        $districtMap = [];
        foreach ($districtModels as $district) {
            $districtMap[$normalizeDistrictKey($district->label)] = (int) $district->id;
            if (!empty($district->name)) {
                $districtMap[$normalizeDistrictKey($district->name)] = (int) $district->id;
            }
        }

        $notApplicableDistrictId = optional($districtModels->firstWhere('name', 'not_applicable'))->id
            ?? ($districtMap[$normalizeDistrictKey('該当なし')] ?? null);

        $val = function (array $row, ?int $index) {
            if ($index === null || !array_key_exists($index, $row)) {
                return null;
            }

            $v = $row[$index];
            if ($v === null) {
                return null;
            }

            $v = trim((string) $v);

            return $v === '' ? null : $v;
        };

        $normalizeNullable = function ($value): ?string {
            if ($value === null) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        $normalizeKana = function ($value): ?string {
            if ($value === null) {
                return null;
            }

            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }

            $value = mb_convert_kana($value, 'CKV', 'UTF-8');

            return $value === '' ? null : $value;
        };

        $normalizeGrade = function ($value): ?string {
            $v = trim((string) $value);

            return in_array($v, ['1級', '2級'], true) ? $v : null;
        };

        $normalizeSex = function ($value) use ($normalizeCompact): ?bool {
            if ($value === null) {
                return null;
            }

            $s = mb_strtolower($normalizeCompact($value), 'UTF-8');
            if ($s === '' || in_array($s, ['?', '？', '不明', '未設定', '未登録', 'null'], true)) {
                return null;
            }
            if (in_array($s, ['1', '男', '男性', 'm', 'male'], true)) {
                return true;
            }
            if (in_array($s, ['2', '女', '女性', 'f', 'female'], true)) {
                return false;
            }

            return null;
        };

        $normalizeDistrict = function ($value) use ($normalizeCompact, $normalizeDistrictKey, $districtMap, $notApplicableDistrictId) {
            if ($value === null) {
                return $notApplicableDistrictId;
            }

            $s = $normalizeCompact($value);
            if ($s === '') {
                return $notApplicableDistrictId;
            }

            if (ctype_digit($s)) {
                return (int) $s;
            }

            $key = $normalizeDistrictKey($s);

            return $districtMap[$key] ?? $notApplicableDistrictId;
        };

        $normalizeFlag = function ($value): bool {
            $s = trim((string) $value);
            if ($s === '') {
                return false;
            }

            return in_array($s, ['〇', '○', '有', '1', 'true', 'TRUE', 'True', 'yes', 'YES', 'Yes'], true);
        };

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $seenSourceKeys = [];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($fh)) !== false) {
                if (!array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                    continue;
                }

                $sourceKey = $normalizeNullable($val($row, $C->id));
                if ($sourceKey === null) {
                    $skipped++;
                    continue;
                }

                $name = $normalizeNullable($val($row, $C->name));
                if ($name === null) {
                    $skipped++;
                    continue;
                }

                $seenSourceKeys[] = $sourceKey;

                $rawDistrict = $val($row, $C->district);
                $nameKana = $normalizeKana($val($row, $C->kana));
                $sex = $normalizeSex($val($row, $C->sex));
                $districtId = $normalizeDistrict($rawDistrict);
                $districtIdForMatch = $rawDistrict === null ? null : $districtId;

                if ($districtIdForMatch !== null && $notApplicableDistrictId !== null && $districtIdForMatch === (int) $notApplicableDistrictId) {
                    $districtIdForMatch = null;
                }

                $coachRaw = $normalizeNullable($val($row, $C->coach));
                $licenseNo = $normalizeNullable($val($row, $C->license));
                $sourceActive = $normalizeFlag($val($row, $C->active));

                $matchedBowler = $this->resolveMatchingProBowler(
                    $licenseNo,
                    $name,
                    $nameKana,
                    $sex,
                    $districtIdForMatch
                );

                $resolvedLicenseNo = $licenseNo ?: $matchedBowler?->license_no;

                $notes = $coachRaw
                    ? 'imported from AuthInstructor.csv / coach=' . $coachRaw
                    : 'imported from AuthInstructor.csv';

                if ($matchedBowler && $licenseNo === null) {
                    $notes .= ' / matched_pro_bowler=' . $matchedBowler->license_no;
                }

                $payload = [
                    'legacy_instructor_license_no' => null,
                    'pro_bowler_id'                => $matchedBowler?->id,
                    'license_no'                   => $resolvedLicenseNo,
                    'cert_no'                      => $sourceKey,
                    'name'                         => $name,
                    'name_kana'                    => $nameKana,
                    'sex'                          => $sex,
                    'district_id'                  => $districtId,
                    'instructor_category'          => 'certified',
                    'grade'                        => $normalizeGrade($val($row, $C->grade)),
                    'coach_qualification'          => $coachRaw !== null && $coachRaw !== '',
                    'source_registered_at'         => null,
                    'is_visible'                   => $normalizeFlag($val($row, $C->visible)),
                    'last_synced_at'               => now(),
                    'notes'                        => $notes,
                ];

                $registry = InstructorRegistry::query()->firstOrNew([
                    'source_type' => 'auth_instructor_csv',
                    'source_key'  => $sourceKey,
                ]);

                $wasNew = !$registry->exists;

                $registry->fill($payload);
                $registry->source_type = 'auth_instructor_csv';
                $registry->source_key = $sourceKey;

                $this->applyCertifiedCurrentState($registry, $sourceActive);

                $registry->save();

                if ($wasNew) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            $this->retireMissingAuthInstructorRows($seenSourceKeys);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($fh);

            return back()->withErrors([
                'csv' => '認定インストラクターCSV取り込み失敗: ' . $e->getMessage(),
            ]);
        }

        fclose($fh);

        return redirect()
            ->route('instructors.index')
            ->with('success', "認定インストラクターCSV取り込み完了：新規 {$created} 件 / 更新 {$updated} 件 / スキップ {$skipped} 件");
    }

    private function applyCertifiedCurrentState(InstructorRegistry $registry, bool $sourceActive): void
    {
        $now = now();
        $currentYear = (int) $now->format('Y');
        $currentDueOn = sprintf('%04d-12-31', $currentYear);

        $registry->renewal_year = $currentYear;
        $registry->renewal_due_on = $currentDueOn;

        if (!$sourceActive) {
            $registry->is_current = false;
            $registry->is_active = false;
            $registry->superseded_at = $now;
            $registry->supersede_reason = 'inactive_in_source';
            $registry->renewal_status = 'expired';
            $registry->renewed_at = null;
            $registry->renewal_note = 'AuthInstructor.csv 取込時に有効フラグなし';
            $registry->last_synced_at = $now;

            return;
        }

        $currentProTarget = $this->findCurrentProTarget($registry);

        if ($currentProTarget) {
            $registry->is_current = false;
            $registry->is_active = false;
            $registry->superseded_at = $now;
            $registry->supersede_reason = $this->resolveCertifiedSupersedeReasonFromProTarget($currentProTarget->instructor_category);
            $registry->renewal_status = 'renewed';
            $registry->renewed_at = $now->toDateString();
            $registry->renewal_note = 'AuthInstructor.csv 年次更新取込済み（現行資格は ' . $currentProTarget->instructor_category . '）';
            $registry->last_synced_at = $now;

            return;
        }

        $registry->is_current = true;
        $registry->is_active = true;
        $registry->superseded_at = null;
        $registry->supersede_reason = null;
        $registry->renewal_status = 'renewed';
        $registry->renewed_at = $now->toDateString();
        $registry->renewal_note = 'AuthInstructor.csv 年次更新取込済み';
        $registry->last_synced_at = $now;
    }

    private function retireMissingAuthInstructorRows(array $seenSourceKeys): void
    {
        $seenSourceKeys = array_values(array_unique($seenSourceKeys));

        $query = InstructorRegistry::query()
            ->where('source_type', 'auth_instructor_csv')
            ->where('is_current', true);

        if (!empty($seenSourceKeys)) {
            $query->whereNotIn('source_key', $seenSourceKeys);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        $currentYear = (int) $now->format('Y');
        $currentDueOn = sprintf('%04d-12-31', $currentYear);

        foreach ($rows as $row) {
            $row->is_current = false;
            $row->is_active = false;
            $row->superseded_at = $now;
            $row->supersede_reason = 'certified_not_renewed';
            $row->renewal_year = $currentYear;
            $row->renewal_due_on = $currentDueOn;
            $row->renewal_status = 'expired';
            $row->renewed_at = null;
            $row->renewal_note = '当年の AuthInstructor.csv に未掲載';
            $row->last_synced_at = $now;
            $row->save();
        }
    }

    private function resolveMatchingProBowler(
        ?string $licenseNo,
        string $name,
        ?string $nameKana,
        ?bool $sex,
        ?int $districtId
    ): ?ProBowler {
        if ($licenseNo !== null && trim($licenseNo) !== '') {
            $byLicense = ProBowler::query()
                ->where('license_no', $licenseNo)
                ->first();

            if ($byLicense) {
                return $byLicense;
            }
        }

        $candidates = [
            [
                'name_kanji'  => $name,
                'name_kana'   => $nameKana,
                'sex'         => $sex,
                'district_id' => $districtId,
            ],
            [
                'name_kanji' => $name,
                'name_kana'  => $nameKana,
                'sex'        => $sex,
            ],
            [
                'name_kanji'  => $name,
                'name_kana'   => $nameKana,
                'district_id' => $districtId,
            ],
            [
                'name_kanji' => $name,
                'name_kana'  => $nameKana,
            ],
            [
                'name_kanji'  => $name,
                'sex'         => $sex,
                'district_id' => $districtId,
            ],
            [
                'name_kanji' => $name,
                'sex'        => $sex,
            ],
            [
                'name_kanji'  => $name,
                'district_id' => $districtId,
            ],
        ];

        foreach ($candidates as $conditions) {
            $matched = $this->findUniqueProBowlerByConditions($conditions);
            if ($matched) {
                return $matched;
            }
        }

        return null;
    }

    private function findUniqueProBowlerByConditions(array $conditions): ?ProBowler
    {
        $activeConditions = array_filter(
            $conditions,
            fn ($value) => $value !== null && $value !== ''
        );

        $hasNameCondition = array_key_exists('name_kanji', $activeConditions) || array_key_exists('name_kana', $activeConditions);

        if (!$hasNameCondition || count($activeConditions) < 2) {
            return null;
        }

        $query = ProBowler::query();

        foreach ($activeConditions as $column => $value) {
            $query->where($column, $value);
        }

        $rows = $query
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($rows->count() !== 1) {
            return null;
        }

        return $rows->first();
    }

    private function findCurrentProTarget(InstructorRegistry $registry): ?InstructorRegistry
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

                if ($registry->license_no !== null && trim($registry->license_no) !== '') {
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

    private function resolveCertifiedSupersedeReasonFromProTarget(string $targetCategory): string
    {
        return match ($targetCategory) {
            'pro_bowler'     => 'promoted_to_pro_bowler',
            'pro_instructor' => 'promoted_to_pro_instructor',
            default          => 'category_changed',
        };
    }
}