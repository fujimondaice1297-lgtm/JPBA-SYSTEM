<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\InstructorRegistry;
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
            'id'         => $col(['#ID', 'ID']),
            'license'    => $col(['ライセンスNo', 'ライセンスNO', 'ライセンスＮｏ']),
            'grade'      => $col(['認定級']),
            'name'       => $col(['名前', '氏名']),
            'kana'       => $col(['名前（フリガナ）', 'フリガナ']),
            'sex'        => $col(['性別']),
            'district'   => $col(['地区']),
            'visible'    => $col(['表示フラグ']),
            'active'     => $col(['有効フラグ']),
            'coach'      => $col(['コーチ資格']),
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

        $normalizeGrade = function ($value) {
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

                $coachRaw = $normalizeNullable($val($row, $C->coach));
                $licenseNo = $normalizeNullable($val($row, $C->license));

                $payload = [
                    'legacy_instructor_license_no' => null,
                    'pro_bowler_id'                => null,
                    'license_no'                   => $licenseNo,
                    'cert_no'                      => $sourceKey,
                    'name'                         => $name,
                    'name_kana'                    => $normalizeNullable($val($row, $C->kana)),
                    'sex'                          => $normalizeSex($val($row, $C->sex)),
                    'district_id'                  => $normalizeDistrict($val($row, $C->district)),
                    'instructor_category'          => 'certified',
                    'grade'                        => $normalizeGrade($val($row, $C->grade)),
                    'coach_qualification'          => $coachRaw !== null && $coachRaw !== '',
                    'source_registered_at'         => null,
                    'is_current'                   => true,
                    'superseded_at'                => null,
                    'supersede_reason'             => null,
                    'is_active'                    => $normalizeFlag($val($row, $C->active)),
                    'is_visible'                   => $normalizeFlag($val($row, $C->visible)),
                    'last_synced_at'               => now(),
                    'notes'                        => $coachRaw
                        ? 'imported from AuthInstructor.csv / coach=' . $coachRaw
                        : 'imported from AuthInstructor.csv',
                ];

                $registry = InstructorRegistry::query()->firstOrNew([
                    'source_type' => 'auth_instructor_csv',
                    'source_key'  => $sourceKey,
                ]);

                $registry->fill($payload);
                $registry->source_type = 'auth_instructor_csv';
                $registry->source_key = $sourceKey;
                $registry->save();

                if ($registry->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

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
}
