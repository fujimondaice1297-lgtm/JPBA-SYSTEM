<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\ProBowler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProBowlerImportController extends Controller
{
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

        $districtMap = District::query()
            ->get(['id', 'label'])
            ->mapWithKeys(function ($district) use ($normalizeCompact) {
                return [$normalizeCompact($district->label) => (int) $district->id];
            })
            ->all();

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

                if (preg_match('/^([A-Z])[^\d]*([0-9]+)$/u', $raw, $m)) {
                    return $m[1] . $m[2];
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

        $district = function ($v) use ($districtMap, $normalizeCompact) {
            if ($v === null) {
                return null;
            }

            $s = $normalizeCompact($v);
            if ($s === '') {
                return null;
            }

            if (ctype_digit($s)) {
                return (int) $s;
            }

            return $districtMap[$s] ?? null;
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
                if (!array_filter($row, fn($v) => $v !== null && $v !== '')) {
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

                [$proYear, $kibetsuFromPro] = $parseProEntry($val($row, $C->pro));
                $kibetsu = $kibetsuFromPro;

                if ($kibetsu === null && $C->kibetsu !== null) {
                    $kibetsu = $int($val($row, $C->kibetsu));
                }

                $mobile = $val($row, $C->telmobile);
                $home = $val($row, $C->telhome);
                $phone = $mobile ?: $home;

                $data = [
                    'license_no'             => $licenseNo,
                    'name_kanji'             => $val($row, $C->name),
                    'name_kana'              => $kana($val($row, $C->kana)),
                    'sex'                    => $sex($val($row, $C->sex)),
                    'district_id'            => $district($val($row, $C->district)),
                    'kibetsu'                => $kibetsu,
                    'membership_type'        => $val($row, $C->membership),
                    'license_issue_date'     => $date($val($row, $C->issue)),
                    'birthdate'              => $date($val($row, $C->birth)),
                    'birthplace'             => $val($row, $C->birthplace),
                    'pro_entry_year'         => $proYear ?? $year4($val($row, $C->pro)),
                    'coach'                  => $val($row, $C->coach),
                    'mailing_preference'     => $mailPref($val($row, $C->mailpref)),
                    'phone_home'             => $normalizePhone($phone),
                    'email'                  => $val($row, $C->email),
                    'organization_name'      => $val($row, $C->org_name),
                    'organization_zip'       => $normalizeZip($val($row, $C->org_zip)),
                    'organization_addr1'     => $val($row, $C->org_addr1),
                    'organization_addr2'     => $val($row, $C->org_addr2),
                    'organization_url'       => $val($row, $C->org_url),
                    'memo'                   => $val($row, $C->memo),
                    'qr_code_path'           => $val($row, $C->qr),
                    'public_image_path'      => $val($row, $C->public_img),
                    'password_change_status' => $chgStatus($val($row, $C->chg)),
                    'height_cm'              => $int($val($row, $C->height)),
                    'weight_kg'              => $int($val($row, $C->weight)),
                    'blood_type'             => $val($row, $C->blood),
                    'public_zip'             => $normalizeZip($val($row, $C->pub_zip)),
                    'public_addr1'           => $val($row, $C->pub_addr1),
                    'public_addr2'           => $val($row, $C->pub_addr2),
                    'mailing_zip'            => $normalizeZip($val($row, $C->send_zip)),
                    'mailing_addr1'          => $val($row, $C->send_addr1),
                    'mailing_addr2'          => $val($row, $C->send_addr2),
                    'association_role'       => $val($row, $C->role),
                    'dominant_arm'           => $normalizeDominantArm($val($row, $C->arm)),
                    'motto'                  => $val($row, $C->motto),
                    'jbc_driller_cert'       => $yesno($val($row, $C->jbc)),
                    'usbc_coach'             => $val($row, $C->usbc),
                    'a_class_status'         => $yesno($val($row, $C->a_s)),
                    'a_class_year'           => $year4($val($row, $C->a_y)),
                    'b_class_status'         => $yesno($val($row, $C->b_s)),
                    'b_class_year'           => $year4($val($row, $C->b_y)),
                    'c_class_status'         => $yesno($val($row, $C->c_s)),
                    'c_class_year'           => $year4($val($row, $C->c_y)),
                    'master_status'          => $yesno($val($row, $C->m_s)),
                    'master_year'            => $year4($val($row, $C->m_y)),
                    'coach_4_status'         => $yesno($val($row, $C->c4_s)),
                    'coach_4_year'           => $year4($val($row, $C->c4_y)),
                    'coach_3_status'         => $yesno($val($row, $C->c3_s)),
                    'coach_3_year'           => $year4($val($row, $C->c3_y)),
                    'coach_1_status'         => $yesno($val($row, $C->c1_s)),
                    'coach_1_year'           => $year4($val($row, $C->c1_y)),
                    'kenkou_status'          => $yesno($val($row, $C->ken_s)),
                    'kenkou_year'            => $year4($val($row, $C->ken_y)),
                    'school_license_status'  => $yesno($val($row, $C->sch_s)),
                    'school_license_year'    => $year4($val($row, $C->sch_y)),
                    'hobby'                  => $val($row, $C->hobby),
                    'bowling_history'        => $val($row, $C->bowl_hist),
                    'other_sports_history'   => $val($row, $C->other_s),
                    'facebook'               => $val($row, $C->fb),
                    'twitter'                => $val($row, $C->tw),
                    'instagram'              => $val($row, $C->ig),
                    'rankseeker'             => $val($row, $C->rank),
                    'selling_point'          => $val($row, $C->sell),
                    'free_comment'           => $val($row, $C->free),
                    'permanent_seed_date'    => $date($val($row, $C->perm), true),
                    'a_license_number'       => $int($val($row, $C->a_num)),
                    'sponsor_a'              => $val($row, $C->s_a),
                    'sponsor_a_url'          => $val($row, $C->s_a_url),
                    'sponsor_b'              => $val($row, $C->s_b),
                    'sponsor_b_url'          => $val($row, $C->s_b_url),
                    'sponsor_c'              => $val($row, $C->s_c),
                    'sponsor_c_url'          => $val($row, $C->s_c_url),
                    'login_id'               => $val($row, $C->login),
                    'equipment_contract'     => $val($row, $C->equip),
                    'coaching_history'       => $val($row, $C->coach_hist),
                ];

                $model = ProBowler::where('license_no', $licenseNo)->first();

                if ($model) {
                    $model->fill($data)->save();
                    $updated++;
                } else {
                    ProBowler::create($data);
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
}