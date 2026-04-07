<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Instructor;
use App\Models\InstructorRegistry;
use App\Models\ProBowler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProBowlerImportController extends Controller
{
    private const PRO_IMPORT_SOURCE_TYPE = 'pro_bowler_csv';

    public function form()
    {
        return view('pro_bowlers.import');
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
            'name'       => $col(['名前', '氏名']),
            'kana'       => $col(['名前（フリガナ）', 'フリガナ']),
            'sex'        => $col(['性別']),
            'district'   => $col(['地区']),
            'pro'        => $col(['プロ入り']),
            'kibetsu'    => $col(['期別']),
            'membership' => $col(['会員種別名']),
            'issue'      => $col(['ライセンス交付日']),
            'birth'      => $col(['生年月日']),
            'birthplace' => $col(['出身地']),
            'coach'      => $col(['師匠・コーチ', '師匠コーチ']),
            'mailpref'   => $col(['郵送区分']),

            'telhome'    => $col(['TEL 自宅', 'TEL自宅']),
            'telmobile'  => $col(['TEL 携帯電話', 'TEL携帯電話', '携帯電話', 'TEL携帯']),

            'email'      => $col(['メールアドレス']),
            'org_name'   => $col(['所属先']),
            'org_zip'    => $col(['所属先 郵便番号', '所属先郵便番号']),
            'org_addr1'  => $col(['所属先住所1']),
            'org_addr2'  => $col(['所属先住所2']),
            'org_url'    => $col(['所属先URL']),
            'memo'       => $col(['備考']),
            'qr'         => $col(['QRコード', 'QRCode']),
            'public_img' => $col(['プロフィール写真']),

            'chg'        => $col(['変更状況']),
            'height'     => $col(['身長']),
            'weight'     => $col(['体重']),
            'blood'      => $col(['血液型']),

            'pub_zip'    => $col(['住所（公開）1（郵便番号）']),
            'pub_addr1'  => $col(['住所（公開）2']),
            'pub_addr2'  => $col(['住所（公開）3']),

            'role'       => $col(['協会役職']),

            'send_zip'   => $col(['住所（送付）1（郵便番号）']),
            'send_addr1' => $col(['住所（送付）2']),
            'send_addr2' => $col(['住所（送付）3']),

            'motto'      => $col(['座右の銘']),
            'arm'        => $col(['利き腕']),

            'jbc'        => $col(['JBC公認ドリラー']),
            'usbc'       => $col(['USBCコーチ']),

            'a_s'        => $col(['A級']),
            'a_y'        => $col(['A級取得年度', 'A級　取得年度']),
            'b_s'        => $col(['B級']),
            'b_y'        => $col(['B級取得年度', 'B級　取得年度']),
            'c_s'        => $col(['C級']),
            'c_y'        => $col(['C級取得年度', 'C級　取得年度']),

            'm_s'        => $col(['マスター', 'マス ター']),
            'm_y'        => $col(['マスターコーチ取得年度', 'マスター コーチ 取得年度']),

            'c4_s'       => $col(['スポーツ協会認定コーチ4']),
            'c4_y'       => $col(['スポーツ協会認定コーチ4取得年度', 'スポーツ協会認定コーチ4　取得年度']),
            'c3_s'       => $col(['スポーツ協会認定コーチ3']),
            'c3_y'       => $col(['スポーツ協会認定コーチ3取得年度', 'スポーツ協会認定コーチ3　取得年度']),
            'c1_s'       => $col(['スポーツ協会認定コーチ1']),
            'c1_y'       => $col(['スポーツ協会認定コーチ1取得年度', 'スポーツ協会認定コーチ1　取得年度']),

            'ken_s'      => $col(['健康ボウリング教室開講資格']),
            'ken_y'      => $col(['健康ボウリング教室開講資格取得年度', '健康ボウリング教室開講資格　取得年度']),
            'sch_s'      => $col(['スクール開講資格']),
            'sch_y'      => $col(['スクール開講資格取得年度', 'スクール開講資格　取得年度']),

            'hobby'      => $col(['趣味・特技']),
            'bowl_hist'  => $col(['ボウリング歴']),
            'other_s'    => $col(['他スポーツ歴']),
            'fb'         => $col(['フェイスブック', 'フ ェイスブック', 'facebook']),
            'tw'         => $col(['ツイッター', 'twitter']),
            'ig'         => $col(['インスタグラム', 'instagram']),
            'rank'       => $col(['ランクシーカー']),
            'sell'       => $col(['セールスポイント']),
            'free'       => $col(['その他自由入力欄', '自由入力欄']),

            'perm'       => $col(['永久シード']),
            'a_num'      => $col(['A級ライセンス取得番号']),

            's_a'        => $col(['スポンサーA']),
            's_a_url'    => $col(['スポンサーA URL', 'スポンサーAURL']),
            's_b'        => $col(['スポンサーB']),
            's_b_url'    => $col(['スポンサーB URL', 'スポンサーBURL']),
            's_c'        => $col(['スポンサーC']),
            's_c_url'    => $col(['スポンサーC URL', 'スポンサーCURL']),

            'login'      => $col(['ログインID']),
            'equip'      => $col(['用品要約']),
            'coach_hist' => $col(['主な指導歴']),
        ];

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

        $license = function ($idRaw, $licenseRaw = null) {
            foreach ([$idRaw, $licenseRaw] as $raw) {
                if ($raw === null) {
                    continue;
                }

                $raw = trim((string) $raw);
                if ($raw === '') {
                    continue;
                }

                $raw = mb_strtoupper($raw, 'UTF-8');
                $raw = preg_replace('/[\x{00A0}\x{3000}\s]+/u', '', $raw);
                $raw = preg_replace('/[^A-Z0-9]/u', '', $raw);

                if ($raw !== '' && preg_match('/[A-Z]/', $raw) && preg_match('/\d/', $raw)) {
                    return $raw;
                }
            }

            return null;
        };

        $kana = function ($s) {
            if ($s === null) {
                return null;
            }

            return mb_convert_kana((string) $s, 'CKV', 'UTF-8');
        };

        $sex = function ($v) use ($normalizeCompact) {
            if ($v === null) {
                return 0;
            }

            $s = mb_strtolower($normalizeCompact($v), 'UTF-8');

            if ($s === '' || in_array($s, ['?', '？', '不明', '未設定', '未登録', 'null'], true)) {
                return 0;
            }

            if (in_array($s, ['1', '男', '男性', 'm', 'male'], true)) {
                return 1;
            }
            if (in_array($s, ['2', '女', '女性', 'f', 'female'], true)) {
                return 2;
            }

            return 0;
        };

        $district = function ($v) use ($districtMap, $normalizeCompact, $normalizeDistrictKey, $notApplicableDistrictId) {
            if ($v === null) {
                return $notApplicableDistrictId;
            }

            $s = $normalizeCompact($v);
            if ($s === '') {
                return $notApplicableDistrictId;
            }

            if (ctype_digit($s)) {
                return (int) $s;
            }

            $k = $normalizeDistrictKey($s);

            return $districtMap[$k] ?? null;
        };

        $parseProEntry = function ($s) {
            $year = null;
            $kibetsu = null;

            if ($s !== null) {
                $raw = trim((string) $s);

                if (preg_match('/((?:19|20)\d{2})/u', $raw, $m)) {
                    $year = (int) $m[1];
                }

                if (preg_match('/(\d{1,3})\s*期/u', $raw, $m)) {
                    $kibetsu = (int) $m[1];
                }
            }

            return [$year, $kibetsu];
        };

        $date = function ($s, bool $yearOnlyToFirstDay = false) {
            if ($s === null) {
                return null;
            }

            $x = trim((string) $s);
            if ($x === '' || $x === '#######') {
                return null;
            }

            $x = str_replace(['年', '月'], '/', $x);
            $x = str_replace('日', '', $x);

            if (preg_match('/^\d{4}$/', $x)) {
                return $yearOnlyToFirstDay ? ($x . '-01-01') : null;
            }

            try {
                return \Carbon\Carbon::parse($x)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $year4 = function ($s) {
            if ($s === null) {
                return null;
            }

            if (preg_match('/((?:19|20)\d{2})/u', (string) $s, $m)) {
                return (int) $m[1];
            }

            return null;
        };

        $yesno = function ($s) use ($normalizeCompact) {
            if ($s === null) {
                return null;
            }

            $v = mb_strtolower($normalizeCompact($s), 'UTF-8');
            if ($v === '') {
                return null;
            }

            if (in_array($v, ['有', 'yes', '1', 'true', 'y'], true)) {
                return '有';
            }
            if (in_array($v, ['無', 'no', '0', 'false', 'n'], true)) {
                return '無';
            }

            return null;
        };

        $mailPref = function ($v) use ($normalizeCompact) {
            if ($v === null) {
                return null;
            }

            $s = $normalizeCompact($v);

            if (in_array($s, ['1', '自宅'], true)) {
                return 1;
            }
            if (in_array($s, ['2', '所属先'], true)) {
                return 2;
            }

            return null;
        };

        $chgStatus = function ($v) {
            $s = trim((string) $v);

            return $s === '2' ? 2 : ($s === '1' ? 1 : ($s === '0' ? 0 : 2));
        };

        $int = function ($v) {
            if ($v === null || $v === '') {
                return null;
            }

            $digits = preg_replace('/\D/u', '', (string) $v);

            return $digits === '' ? null : (int) $digits;
        };

        $normalizePhone = function ($v) {
            if ($v === null) {
                return null;
            }

            $s = trim(mb_convert_kana((string) $v, 'n', 'UTF-8'));
            $s = preg_replace('/[^\d\+]/u', '', $s);

            return $s ?: null;
        };

        $normalizeZip = function ($v) {
            if ($v === null) {
                return null;
            }

            $s = mb_convert_kana(trim((string) $v), 'as', 'UTF-8');
            $s = preg_replace('/[^0-9\-]/u', '', $s);

            return $s === '' ? null : $s;
        };

        $normalizeDominantArm = function ($v) use ($normalizeCompact) {
            if ($v === null) {
                return null;
            }

            $s = $normalizeCompact($v);
            if ($s === '') {
                return null;
            }

            if (preg_match('/両|both/i', $s)) {
                return '両';
            }
            if (preg_match('/左|left/i', $s)) {
                return '左';
            }
            if (preg_match('/右|right/i', $s)) {
                return '右';
            }

            return null;
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

                $licenseNo = $license(
                    $val($row, $C->id),
                    $val($row, $C->license)
                );

                if ($licenseNo === null) {
                    $skipped++;
                    continue;
                }

                $membershipType = $val($row, $C->membership);
                $derivedIsActive = $this->resolveIsActiveFromMembershipType($membershipType);
                $memberClass = $this->resolveMemberClass($membershipType, $licenseNo);

                $isTeachingPro = $memberClass === 'pro_instructor';

                [$proYear, $kibetsuFromPro] = $parseProEntry($val($row, $C->pro));
                $kibetsu = $kibetsuFromPro;

                if ($kibetsu === null && $C->kibetsu !== null) {
                    $kibetsu = $int($val($row, $C->kibetsu));
                }

                if ($isTeachingPro) {
                    $kibetsu = null;
                }

                $mobile = $val($row, $C->telmobile);
                $home = $val($row, $C->telhome);
                $phone = $mobile ?: $home;

                $data = [
                    'license_no'                    => $licenseNo,
                    'name_kanji'                    => $val($row, $C->name),
                    'name_kana'                     => $kana($val($row, $C->kana)),
                    'sex'                           => $sex($val($row, $C->sex)),
                    'district_id'                   => $district($val($row, $C->district)),
                    'kibetsu'                       => $kibetsu,
                    'membership_type'               => $membershipType,
                    'license_issue_date'            => $date($val($row, $C->issue)),
                    'birthdate'                     => $date($val($row, $C->birth)),
                    'birthplace'                    => $val($row, $C->birthplace),
                    'pro_entry_year'                => $proYear ?? $year4($val($row, $C->pro)),
                    'coach'                         => $val($row, $C->coach),
                    'mailing_preference'            => $mailPref($val($row, $C->mailpref)),
                    'phone_home'                    => $normalizePhone($phone),
                    'email'                         => $val($row, $C->email),
                    'organization_name'             => $val($row, $C->org_name),
                    'organization_zip'              => $normalizeZip($val($row, $C->org_zip)),
                    'organization_addr1'            => $val($row, $C->org_addr1),
                    'organization_addr2'            => $val($row, $C->org_addr2),
                    'organization_url'              => $val($row, $C->org_url),
                    'memo'                          => $val($row, $C->memo),
                    'qr_code_path'                  => $val($row, $C->qr),
                    'public_image_path'             => $val($row, $C->public_img),
                    'password_change_status'        => $chgStatus($val($row, $C->chg)),
                    'height_cm'                     => $int($val($row, $C->height)),
                    'weight_kg'                     => $int($val($row, $C->weight)),
                    'blood_type'                    => $val($row, $C->blood),
                    'public_zip'                    => $normalizeZip($val($row, $C->pub_zip)),
                    'public_addr1'                  => $val($row, $C->pub_addr1),
                    'public_addr2'                  => $val($row, $C->pub_addr2),
                    'mailing_zip'                   => $normalizeZip($val($row, $C->send_zip)),
                    'mailing_addr1'                 => $val($row, $C->send_addr1),
                    'mailing_addr2'                 => $val($row, $C->send_addr2),
                    'association_role'              => $val($row, $C->role),
                    'dominant_arm'                  => $normalizeDominantArm($val($row, $C->arm)),
                    'motto'                         => $val($row, $C->motto),
                    'jbc_driller_cert'              => $yesno($val($row, $C->jbc)),
                    'usbc_coach'                    => $val($row, $C->usbc),
                    'a_class_status'                => $yesno($val($row, $C->a_s)),
                    'a_class_year'                  => $year4($val($row, $C->a_y)),
                    'b_class_status'                => $yesno($val($row, $C->b_s)),
                    'b_class_year'                  => $year4($val($row, $C->b_y)),
                    'c_class_status'                => $yesno($val($row, $C->c_s)),
                    'c_class_year'                  => $year4($val($row, $C->c_y)),
                    'master_status'                 => $yesno($val($row, $C->m_s)),
                    'master_year'                   => $year4($val($row, $C->m_y)),
                    'coach_4_status'                => $yesno($val($row, $C->c4_s)),
                    'coach_4_year'                  => $year4($val($row, $C->c4_y)),
                    'coach_3_status'                => $yesno($val($row, $C->c3_s)),
                    'coach_3_year'                  => $year4($val($row, $C->c3_y)),
                    'coach_1_status'                => $yesno($val($row, $C->c1_s)),
                    'coach_1_year'                  => $year4($val($row, $C->c1_y)),
                    'kenkou_status'                 => $yesno($val($row, $C->ken_s)),
                    'kenkou_year'                   => $year4($val($row, $C->ken_y)),
                    'school_license_status'         => $yesno($val($row, $C->sch_s)),
                    'school_license_year'           => $year4($val($row, $C->sch_y)),
                    'hobby'                         => $val($row, $C->hobby),
                    'bowling_history'               => $val($row, $C->bowl_hist),
                    'other_sports_history'          => $val($row, $C->other_s),
                    'facebook'                      => $val($row, $C->fb),
                    'twitter'                       => $val($row, $C->tw),
                    'instagram'                     => $val($row, $C->ig),
                    'rankseeker'                    => $val($row, $C->rank),
                    'selling_point'                 => $val($row, $C->sell),
                    'free_comment'                  => $val($row, $C->free),
                    'permanent_seed_date'           => $date($val($row, $C->perm), true),
                    'a_license_number'              => $int($val($row, $C->a_num)),
                    'sponsor_a'                     => $val($row, $C->s_a),
                    'sponsor_a_url'                 => $val($row, $C->s_a_url),
                    'sponsor_b'                     => $val($row, $C->s_b),
                    'sponsor_b_url'                 => $val($row, $C->s_b_url),
                    'sponsor_c'                     => $val($row, $C->s_c),
                    'sponsor_c_url'                 => $val($row, $C->s_c_url),
                    'login_id'                      => $val($row, $C->login),
                    'equipment_contract'            => $val($row, $C->equip),
                    'coaching_history'              => $val($row, $C->coach_hist),
                    'is_active'                     => $derivedIsActive,
                    'member_class'                  => $memberClass,
                    'can_enter_official_tournament' => $memberClass === 'player' && $derivedIsActive,
                ];

                $model = ProBowler::where('license_no', $licenseNo)->first();

                if ($model) {
                    if ($data['district_id'] === null) {
                        $data['district_id'] = $model->district_id;
                    }
                    if ($data['kibetsu'] === null && !$isTeachingPro) {
                        $data['kibetsu'] = $model->kibetsu;
                    }
                    if ($data['pro_entry_year'] === null) {
                        $data['pro_entry_year'] = $model->pro_entry_year;
                    }

                    $model->fill($data)->save();
                    $this->syncInstructorRecordsFromBowler($model);
                    $updated++;
                } else {
                    $createdBowler = ProBowler::create($data);
                    $this->syncInstructorRecordsFromBowler($createdBowler);
                    $created++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($fh);

            return back()->withErrors([
                'csv' => '取り込み失敗: ' . $e->getMessage(),
            ]);
        }

        fclose($fh);

        return redirect()
            ->route('pro_bowlers.index')
            ->with('success', "CSV取り込み完了：新規 {$created} 件 / 更新 {$updated} 件 / スキップ {$skipped} 件");
    }

    private function syncInstructorRecordsFromBowler(ProBowler $bowler): void
    {
        $grade = $this->resolveInstructorGradeFromBowler($bowler);
        $category = $this->resolveInstructorCategoryFromBowler($bowler);

        if (!$this->hasInstructorRegistryTarget($bowler, $grade, $category)) {
            $reactivatedCertified = $this->reactivateCertifiedCurrentRowForBowler($bowler);

            $this->deactivateInstructorRecordsFromBowler(
                $bowler,
                $reactivatedCertified ? 'downgraded_to_certified' : 'qualification_removed'
            );

            return;
        }

        $this->syncLegacyInstructorFromBowler($bowler, $grade, $category);
        $targetRegistry = $this->syncRegistryFromBowler($bowler, $grade, $category);
        $this->supersedeCompetingCurrentRegistryRowsForBowler($bowler, $targetRegistry);
    }

    private function syncLegacyInstructorFromBowler(ProBowler $bowler, ?string $grade, string $category): void
    {
        $existing = Instructor::query()
            ->where('license_no', $bowler->license_no)
            ->first();

        $payload = [
            'license_no'          => $bowler->license_no,
            'name'                => $bowler->name_kanji,
            'name_kana'           => $bowler->name_kana,
            'sex'                 => ((int) ($bowler->sex ?? 0)) === 1,
            'district_id'         => $bowler->district_id,
            'instructor_type'     => 'pro',
            'grade'               => $grade,
            'is_active'           => (bool) $bowler->is_active,
            'is_visible'          => $existing?->is_visible ?? true,
            'coach_qualification' => ($bowler->school_license_status ?? null) === '有',
            'pro_bowler_id'       => $bowler->id,
        ];

        Instructor::updateOrCreate(
            ['license_no' => $bowler->license_no],
            $payload
        );
    }

    private function syncRegistryFromBowler(ProBowler $bowler, ?string $grade, string $category): InstructorRegistry
    {
        $existing = $this->importedRegistryQueryForBowlerAndCategory($bowler, $category)
            ->orderByDesc('is_current')
            ->orderByDesc('last_synced_at')
            ->orderBy('id')
            ->first();

        $payload = [
            'source_type'                  => self::PRO_IMPORT_SOURCE_TYPE,
            'source_key'                   => $existing?->source_key ?: $this->buildProRegistrySourceKey($bowler->license_no, $category),
            'legacy_instructor_license_no' => $existing?->legacy_instructor_license_no ?? $bowler->license_no,
            'pro_bowler_id'                => $bowler->id,
            'license_no'                   => $bowler->license_no,
            'cert_no'                      => $existing?->cert_no,
            'name'                         => $bowler->name_kanji ?: ($existing?->name ?? $bowler->license_no),
            'name_kana'                    => $bowler->name_kana,
            'sex'                          => $this->normalizeRegistrySex($bowler),
            'district_id'                  => $bowler->district_id,
            'instructor_category'          => $category,
            'grade'                        => $grade,
            'coach_qualification'          => ($bowler->school_license_status ?? null) === '有',
            'is_active'                    => (bool) $bowler->is_active,
            'is_visible'                   => $existing?->is_visible ?? true,
            'source_registered_at'         => $bowler->license_issue_date ?: ($existing?->source_registered_at?->format('Y-m-d H:i:s') ?? null),
            'is_current'                   => true,
            'superseded_at'                => null,
            'supersede_reason'             => null,
            'renewal_year'                 => $existing?->renewal_year ?? $this->currentRenewalYear(),
            'renewal_due_on'               => $existing?->renewal_due_on?->format('Y-m-d') ?? $this->currentRenewalDueDate(),
            'renewal_status'               => $existing?->renewal_status ?? 'pending',
            'renewed_at'                   => $existing?->renewed_at?->format('Y-m-d'),
            'renewal_note'                 => $existing?->renewal_note,
            'last_synced_at'               => now(),
            'notes'                        => $existing?->notes ?: 'synced from pro_bowlers import',
        ];

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        return InstructorRegistry::create($payload);
    }

    private function deactivateInstructorRecordsFromBowler(ProBowler $bowler, string $supersedeReason): void
    {
        $this->deactivateLegacyInstructorFromBowler($bowler);
        $this->deactivateRegistryFromBowler($bowler, $supersedeReason);
    }

    private function deactivateLegacyInstructorFromBowler(ProBowler $bowler): void
    {
        $existing = Instructor::query()
            ->where('license_no', $bowler->license_no)
            ->first();

        if (!$existing) {
            return;
        }

        if (!$existing->is_active) {
            return;
        }

        $existing->is_active = false;
        $existing->save();
    }

    private function deactivateRegistryFromBowler(ProBowler $bowler, string $supersedeReason): void
    {
        $rows = $this->proSideRegistryQueryForBowler($bowler)
            ->where('is_current', true)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($rows as $row) {
            $row->is_current = false;
            $row->is_active = false;
            $row->superseded_at = $row->superseded_at ?: $now;
            $row->supersede_reason = $supersedeReason;
            $row->last_synced_at = $now;
            $row->save();
        }
    }

    private function reactivateCertifiedCurrentRowForBowler(ProBowler $bowler): bool
    {
        $certified = $this->allRegistryQueryForBowler($bowler)
            ->where('instructor_category', 'certified')
            ->where(function ($query) {
                $query->whereNull('renewal_status')
                    ->orWhere('renewal_status', '!=', 'expired');
            })
            ->orderByDesc('is_current')
            ->orderByDesc('last_synced_at')
            ->orderBy('id')
            ->first();

        if (!$certified) {
            return false;
        }

        $certified->is_current = true;
        $certified->is_active = true;
        $certified->superseded_at = null;
        $certified->supersede_reason = null;
        $certified->renewal_year = $certified->renewal_year ?? $this->currentRenewalYear();
        $certified->renewal_due_on = $certified->renewal_due_on ?? $this->currentRenewalDueDate();
        $certified->renewal_status = $certified->renewal_status ?? 'pending';
        $certified->last_synced_at = now();
        $certified->save();

        return true;
    }

    private function supersedeCompetingCurrentRegistryRowsForBowler(ProBowler $bowler, InstructorRegistry $targetRegistry): void
    {
        $rows = $this->allRegistryQueryForBowler($bowler)
            ->where('is_current', true)
            ->where('id', '!=', $targetRegistry->id)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($rows as $row) {
            $row->is_current = false;
            $row->is_active = false;
            $row->superseded_at = $now;
            $row->supersede_reason = $this->resolveSupersedeReason($row->instructor_category, $targetRegistry->instructor_category);
            $row->last_synced_at = $now;
            $row->save();
        }
    }

    private function importedRegistryQueryForBowlerAndCategory(ProBowler $bowler, string $category)
    {
        return $this->allRegistryQueryForBowler($bowler)
            ->where('source_type', self::PRO_IMPORT_SOURCE_TYPE)
            ->where('instructor_category', $category);
    }

    private function proSideRegistryQueryForBowler(ProBowler $bowler)
    {
        return $this->allRegistryQueryForBowler($bowler)
            ->whereIn('instructor_category', ['pro_bowler', 'pro_instructor']);
    }

    private function allRegistryQueryForBowler(ProBowler $bowler)
    {
        return InstructorRegistry::query()
            ->where(function ($query) use ($bowler) {
                $query->where(function ($q) use ($bowler) {
                    $q->whereNotNull('pro_bowler_id')
                        ->where('pro_bowler_id', $bowler->id);
                })->orWhere(function ($q) use ($bowler) {
                    $q->whereNotNull('license_no')
                        ->where('license_no', $bowler->license_no);
                })->orWhere(function ($q) use ($bowler) {
                    $q->whereNotNull('legacy_instructor_license_no')
                        ->where('legacy_instructor_license_no', $bowler->license_no);
                });
            });
    }

    private function buildProRegistrySourceKey(string $licenseNo, string $category): string
    {
        return $licenseNo . ':' . $category;
    }

    private function resolveSupersedeReason(string $fromCategory, string $toCategory): string
    {
        return match (true) {
            $fromCategory === 'certified' && $toCategory === 'pro_instructor' => 'promoted_to_pro_instructor',
            $fromCategory === 'certified' && $toCategory === 'pro_bowler'     => 'promoted_to_pro_bowler',
            $fromCategory === 'pro_instructor' && $toCategory === 'pro_bowler' => 'promoted_to_pro_bowler',
            $fromCategory === 'pro_bowler' && $toCategory === 'certified'     => 'downgraded_to_certified',
            $fromCategory === 'pro_instructor' && $toCategory === 'certified' => 'downgraded_to_certified',
            $fromCategory === $toCategory                                      => 'replaced_by_pro_bowler_import',
            default                                                            => 'category_changed',
        };
    }

    private function currentRenewalYear(): int
    {
        return (int) now()->format('Y');
    }

    private function currentRenewalDueDate(): string
    {
        return sprintf('%04d-12-31', $this->currentRenewalYear());
    }

    private function resolveInstructorGradeFromBowler(ProBowler $bowler): ?string
    {
        return match (true) {
            ($bowler->a_class_status ?? null) === '有' => 'A級',
            ($bowler->b_class_status ?? null) === '有' => 'B級',
            ($bowler->c_class_status ?? null) === '有' => 'C級',
            default => null,
        };
    }

    private function hasInstructorRegistryTarget(ProBowler $bowler, ?string $grade, string $category): bool
    {
        if ($category === 'pro_instructor') {
            return true;
        }

        return $grade !== null
            || ($bowler->master_status ?? null) === '有'
            || ($bowler->school_license_status ?? null) === '有'
            || ($bowler->coach_4_status ?? null) === '有'
            || ($bowler->coach_3_status ?? null) === '有'
            || ($bowler->coach_1_status ?? null) === '有'
            || ($bowler->kenkou_status ?? null) === '有';
    }

    private function resolveInstructorCategoryFromBowler(ProBowler $bowler): string
    {
        return ($bowler->member_class ?? null) === 'pro_instructor'
            ? 'pro_instructor'
            : 'pro_bowler';
    }

    private function resolveIsActiveFromMembershipType(?string $membershipType): bool
    {
        $value = trim((string) $membershipType);

        return !in_array($value, ['死亡', '除名', '退会届'], true);
    }

    private function resolveMemberClass(?string $membershipType, string $licenseNo): string
    {
        $value = trim((string) $membershipType);

        if (in_array($value, ['プロインストラクター', '認定プロインストラクター'], true) || $this->isTeachingProLicense($licenseNo)) {
            return 'pro_instructor';
        }

        if (in_array($value, ['その他', '海外'], true)) {
            return 'honorary_or_overseas';
        }

        return 'player';
    }

    private function isTeachingProLicense(string $licenseNo): bool
    {
        $normalized = strtoupper(trim($licenseNo));

        return preg_match('/^T\d+$/', $normalized) === 1
            || preg_match('/^[A-Z]\d{4}T\d{3,4}$/', $normalized) === 1;
    }

    private function normalizeRegistrySex(ProBowler $bowler): ?bool
    {
        return match ((int) ($bowler->sex ?? 0)) {
            1       => true,
            2       => false,
            default => null,
        };
    }
}