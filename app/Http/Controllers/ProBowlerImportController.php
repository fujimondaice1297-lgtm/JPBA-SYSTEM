<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProBowlerImportController extends Controller
{
    public function form() { return view('pro_bowlers.import'); }

    public function import(Request $req)
    {
        $req->validate(['csv' => 'required|file|mimes:csv,txt']);
        $fh = fopen($req->file('csv')->getRealPath(), 'r');
        if (!$fh) return back()->withErrors(['csv'=>'CSVを開けません']);

        // Excel想定: CP932 → UTF-8
        @stream_filter_append($fh, 'convert.iconv.CP932/UTF-8');

        $header = fgetcsv($fh);
        if (!$header) return back()->withErrors(['csv'=>'ヘッダーが空です']);

        $norm = fn($s)=>mb_strtolower(preg_replace('/[　\s]/u','',(string)$s));
        $keys = array_map($norm, $header);

        // —— ヘッダー→列番号（指示書版）
        $col = function(array $aliases) use($keys,$norm){
            foreach ($aliases as $a) { $i=array_search($norm($a),$keys,true); if($i!==false) return $i; }
            return null;
        };

        $C = (object)[
            'id'                => $col(['#id','id','ライセンスno']),
            'name'              => $col(['名前','氏名']),
            'kana'              => $col(['名前（フリガナ）','フリガナ']),
            'sex'               => $col(['性別']),
            'district'          => $col(['地区']),
            'pro'               => $col(['プロ入り']),
            'kibetsu'           => $col(['期別']), // ←基本は pro から抽出、あるなら優先しない
            'membership'        => $col(['会員種別名']),
            'issue'             => $col(['ライセンス交付日']),
            'birth'             => $col(['生年月日']),
            'birthplace'        => $col(['出身地']),
            'coach'             => $col(['師匠・コーチ']),
            'mailpref'          => $col(['郵送区分']),
            'telhome'           => $col(['tel 自宅','tel自宅']),
            'email'             => $col(['メールアドレス']),
            'org_name'          => $col(['所属先']),
            'org_zip'           => $col(['所属先《郵便番号》','所属先郵便番号']),
            'org_addr1'         => $col(['所属先住所1']),
            'org_addr2'         => $col(['所属先住所2']),
            'org_url'           => $col(['所属先url','勤務先url']),
            'memo'              => $col(['備考']),
            'qr'                => $col(['qrコード','qrcode']),
            'public_img'        => $col(['プロフィール写真']),
            'jbc'               => $col(['jbc公認ドリラー']),
            'usbc'              => $col(['usbcコーチ']),
            'a_s'               => $col(['a級']),
            'a_y'               => $col(['a級 取得年度']),
            'b_s'               => $col(['b級']),
            'b_y'               => $col(['b級 取得年度']),
            'c_s'               => $col(['c級']),
            'c_y'               => $col(['c級 取得年度']),
            'm_s'               => $col(['マスター']),
            'm_y'               => $col(['マスター 取得年度']),
            'c4_s'              => $col(['スポーツ協会認定コーチ4']),
            'c4_y'              => $col(['スポーツ協会認定コーチ4 取得年度']),
            'c3_s'              => $col(['スポーツ協会認定コーチ3']),
            'c3_y'              => $col(['スポーツ協会認定コーチ3 取得年度']),
            'c1_s'              => $col(['スポーツ協会認定コーチ1']),
            'c1_y'              => $col(['スポーツ協会認定コーチ1 取得年度']),
            'ken_s'             => $col(['健康ボウリング教室開講資格']),
            'ken_y'             => $col(['健康ボウリング教室開講資格 取得年度']),
            'sch_s'             => $col(['スクール開講資格']),
            'sch_y'             => $col(['スクール開講資格 取得年度']),
            'hobby'             => $col(['趣味・特技']),
            'bowl_hist'         => $col(['ボウリング歴']),
            'other_s'           => $col(['他スポーツ歴']),
            'fb'                => $col(['フェイスブック']),
            'tw'                => $col(['ツイッター']),
            'ig'                => $col(['インスタグラム']),
            'rank'              => $col(['ランクシーカー']),
            'sell'              => $col(['セールスポイント']),
            'free'              => $col(['その他自由入力欄']),
            'perm'              => $col(['永久シード']),
            'a_num'             => $col(['a級ライセンス取得番号','a級ライセンス取得年月']), // 後者は番号列に使わないが保険
            'chg'               => $col(['変更状況']),
            'height'            => $col(['身長']),
            'weight'            => $col(['体重']),
            'blood'             => $col(['血液型']),
            'telmobile'         => $col(['tel 携帯電話','携帯電話','携帯tel','携帯','携帯番号','tel携帯']),
            'telhome'           => $col(['tel 自宅','tel自宅','自宅tel','電話','tel']),
            'pub_zip'           => $col(['住所（公開）1']),
            'pub_addr1'         => $col(['住所（公開）2']),
            'pub_addr2'         => $col(['住所（公開）3']),
            'role'              => $col(['協会役職']),
            'send_zip'          => $col(['住所（送付）1']),
            'send_addr1'        => $col(['住所（送付）2']),
            'send_addr2'        => $col(['住所（送付）3']),
            'motto'             => $col(['座右の銘']),
            'arm'               => $col(['利き腕']),
            's_a'               => $col(['スポンサーa']),
            's_a_url'           => $col(['スポンサーa url']),
            's_b'               => $col(['スポンサーb']),
            's_b_url'           => $col(['スポンサーb url']),
            's_c'               => $col(['スポンサーc']),
            's_c_url'           => $col(['スポンサーc url']),
            'login'             => $col(['ログインid']),
            'equip'             => $col(['用品契約']),
            'coach_hist'        => $col(['主な指導歴']),
            'selfpr'            => $col(['自己pr']),
        ];

        // —— 変換ユーティリティ
        $license = function ($raw) {
            // 例: F00000129 → F0129（先頭の英字 + 末尾数字4桁でゼロパディング）
            if ($raw === null) return null;
            $raw = trim((string)$raw);
            if ($raw === '') return null;
            $letter = strtoupper(substr($raw,0,1));
            preg_match('/(\d+)/', $raw, $m);
            $num = isset($m[1]) ? (int)$m[1] : 0;
            return $letter . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
        };
        $kana = fn($s)=> $s===null?null:mb_convert_kana($s,'CKV'); // 全角カナに統一
        $sex  = function($v){
            $s = trim((string)$v);
            if ($s==='') return null;
            $s = mb_strtolower($s);
            if (in_array($s,['1','男','男性','m','male'])) return 1;
            if (in_array($s,['2','女','女性','f','female'])) return 2;
            return null;
        };
        $district = function($v){
            if ($v===null || $v==='') return null;
            if (ctype_digit((string)$v)) return (int)$v;
            return District::where('label',$v)->value('id'); // 見つからなければ null
        };
        $proYearKibetsu = function($s){
            // "1973年 5期生" → [1973,5]
            $year = null; $k=null;
            if ($s!==null) {
                if (preg_match('/(\d{4})/u', $s, $m)) $year = (int)$m[1];
                if (preg_match('/(\d+)\s*期/u', $s, $m)) $k = (int)$m[1];
            }
            return [$year,$k];
        };
        $date = function($s,$yearOnlyToFirstDay=false){
            if ($s===null) return null;
            $x = trim((string)$s);
            if ($x==='' || $x==='#######') return null;
            $x = preg_replace('/年|月/u','/',$x); $x = preg_replace('/日/u','',$x);
            if (preg_match('/^\d{4}$/',$x)) return $yearOnlyToFirstDay?($x.'-01-01'):null;
            try { return \Carbon\Carbon::parse($x)->toDateString(); } catch(\Throwable){ return null; }
        };
        $year4 = fn($s)=> (preg_match('/\d{4}/',(string)$s,$m)?(int)$m[0]:null);
        $yesno = fn($s)=> ($s===null||$s==='')?null:(in_array(mb_strtolower((string)$s),['有','yes','1','○'])?'有':'無');
        $mailPref = function($v){
            $s = mb_strtolower((string)$v);
            if (in_array($s,['1','自宅'])) return 1;
            if (in_array($s,['2','勤務先'])) return 2;
            return null;
        };
        $chgStatus = function($v){
            $s = trim((string)$v);
            // 変更状況: 2=未更新,1=確認中,0=更新済
            return $s==='2' ? 2 : ($s==='1' ? 1 : ($s==='0' ? 0 : 2));
        };
        $int = fn($v)=> ($v===null||$v==='')?null:(int)preg_replace('/\D/','',(string)$v);

        $created=0; $updated=0;

        // 便利関数（ファイル上部のユーティリティ群の近くに追加してOK）
        $normalizePhone = function ($v) {
            if ($v === null) return null;
            $s = trim(mb_convert_kana((string)$v, 'n'));      // 全角数字→半角
            $s = preg_replace('/[^\d\+]/', '', $s);          // 数字と+以外を除去
            return $s ?: null;
        };

        DB::beginTransaction();
        try{
            while(($row=fgetcsv($fh))!==false){
                if (!array_filter($row, fn($v)=>$v!==null && $v!=='')) continue;

                $val = fn($i)=> ($i===null?null:(isset($row[$i])?$row[$i]:null));

                // ライセンスNo
                $licenseNo = $license($val($C->id));

                // プロ入りと期別
                [$py,$kb] = $proYearKibetsu($val($C->pro));
                if ($kb===null && $C->kibetsu!==null) $kb = $int($val($C->kibetsu));

                $mobile = $val($C->telmobile);
                $home   = $val($C->telhome);
                $phone  = $mobile !== null && $mobile !== '' ? $mobile : $home;

                $data = [
                    'license_no'      => $licenseNo,
                    'name_kanji'      => $val($C->name),
                    'name_kana'       => $kana($val($C->kana)),
                    'sex'             => $sex($val($C->sex)),
                    'district_id'     => $district($val($C->district)),
                    'kibetsu'         => $kb,
                    'membership_type' => $val($C->membership),
                    'license_issue_date'=> $date($val($C->issue)),
                    'birthdate'       => $date($val($C->birth)),
                    'birthplace'      => $val($C->birthplace),
                    'pro_entry_year'  => $py ?? $year4($val($C->pro)),
                    'coach'           => $val($C->coach),
                    'mailing_preference'=> $mailPref($val($C->mailpref)),
                    'phone_home' => $normalizePhone($phone),
                    'email'           => $val($C->email),
                    'organization_name'=> $val($C->org_name),
                    'organization_zip'=> $val($C->org_zip),
                    'organization_addr1'=> $val($C->org_addr1),
                    'organization_addr2'=> $val($C->org_addr2),
                    'organization_url'=> $val($C->org_url),
                    'memo'            => $val($C->memo),
                    'qr_code_path'    => $val($C->qr),
                    'public_image_path'=> $val($C->public_img),

                    // 変更状況→password_change_status
                    'password_change_status' => $chgStatus($val($C->chg)),

                    // 体情報 + 公開フラグ（初期は非公開）
                    'height_cm'       => $int($val($C->height)),
                    'weight_kg'       => $int($val($C->weight)),
                    'blood_type'      => $val($C->blood),

                    // 公開住所
                    'public_zip'      => $val($C->pub_zip),
                    'public_addr1'    => $val($C->pub_addr1),
                    'public_addr2'    => $val($C->pub_addr2),

                    // 送付先住所
                    'mailing_zip'     => $val($C->send_zip),
                    'mailing_addr1'   => $val($C->send_addr1),
                    'mailing_addr2'   => $val($C->send_addr2),

                    // 役職・利き腕・座右の銘
                    'association_role'=> $val($C->role),
                    'dominant_arm'    => $val($C->arm),
                    'motto'           => $val($C->motto),

                    // 資格・年度
                    'jbc_driller_cert'=> $val($C->jbc) ? $yesno($val($C->jbc)) : null,
                    'usbc_coach'      => $val($C->usbc),
                    'a_class_status'  => $yesno($val($C->a_s)),
                    'a_class_year'    => $year4($val($C->a_y)),
                    'b_class_status'  => $yesno($val($C->b_s)),
                    'b_class_year'    => $year4($val($C->b_y)),
                    'c_class_status'  => $yesno($val($C->c_s)),
                    'c_class_year'    => $year4($val($C->c_y)),
                    'master_status'   => $yesno($val($C->m_s)),
                    'master_year'     => $year4($val($C->m_y)),
                    'coach_4_status'  => $yesno($val($C->c4_s)),
                    'coach_4_year'    => $year4($val($C->c4_y)),
                    'coach_3_status'  => $yesno($val($C->c3_s)),
                    'coach_3_year'    => $year4($val($C->c3_y)),
                    'coach_1_status'  => $yesno($val($C->c1_s)),
                    'coach_1_year'    => $year4($val($C->c1_y)),
                    'kenkou_status'   => $yesno($val($C->ken_s)),
                    'kenkou_year'     => $year4($val($C->ken_y)),
                    'school_license_status'=> $yesno($val($C->sch_s)),
                    'school_license_year'  => $year4($val($C->sch_y)),

                    // 経歴・SNS
                    'hobby'               => $val($C->hobby),
                    'bowling_history'     => $val($C->bowl_hist),
                    'other_sports_history'=> $val($C->other_s),
                    'facebook'            => $val($C->fb),
                    'twitter'             => $val($C->tw),
                    'instagram'           => $val($C->ig),
                    'rankseeker'          => $val($C->rank),
                    'selling_point'       => $val($C->sell),
                    'free_comment'        => $val($C->free),

                    // 永久シード（年だけなら YYYY-01-01 ）
                    'permanent_seed_date' => $date($val($C->perm), true),

                    // A級ライセンス取得番号（整数）
                    'a_license_number'    => $int($val($C->a_num)),

                    // スポンサー等
                    'sponsor_a'     => $val($C->s_a),
                    'sponsor_a_url' => $val($C->s_a_url),
                    'sponsor_b'     => $val($C->s_b),
                    'sponsor_b_url' => $val($C->s_b_url),
                    'sponsor_c'     => $val($C->s_c),
                    'sponsor_c_url' => $val($C->s_c_url),

                    // ログイン・契約・指導
                    'login_id'          => $val($C->login),
                    'equipment_contract'=> $val($C->equip),
                    'coaching_history'  => $val($C->coach_hist),

                    // 自己PRは selling_point とは別に持つ？ → 指示書は selling_point そのまま、自己PRは別
                    'selling_point'     => $val($C->sell),
                    'free_comment'      => $val($C->free),
                ];

                // UPSERT: license_no がキー
                $model = ProBowler::where('license_no',$licenseNo)->first();
                if ($model) { $model->fill($data)->save(); $updated++; }
                else        { ProBowler::create($data);     $created++; }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($fh);
            return back()->withErrors(['csv'=>'取り込み失敗: '.$e->getMessage()]);
        }
        fclose($fh);

        return redirect()->route('pro_bowlers.index')
            ->with('success',"CSV取り込み完了：新規{$created}件／更新{$updated}件");
    }
}
