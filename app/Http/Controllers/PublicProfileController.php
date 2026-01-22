<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use Illuminate\Support\Str;

class PublicProfileController extends Controller
{
    /** /storage 付与などURL正規化 */
    private function storageUrl(?string $p): ?string
    {
        if (!$p) return null;
        if (Str::startsWith($p, ['http://','https://','/'])) return $p;
        return '/storage/' . ltrim($p, '/');
    }

    /** 英字接頭辞を除いたライセンス数字部 */
    private function licenseDigits(?string $lic): string
    {
        return $lic ? preg_replace('/^[A-Za-z]+/', '', $lic) : '';
    }

    /** 生年月日（公開用）表示文字列 */
    private function formatPublicBirth(?string $ymd, bool $hideYear, bool $isPrivate): string
    {
        if ($isPrivate) return '—';
        if (!$ymd) return '—';
        // $ymd: Y-m-d
        [$Y,$m,$d] = explode('-', $ymd) + [null,null,null];
        if ($hideYear) return sprintf('%02d月%02d日', (int)$m, (int)$d);
        return sprintf('%04d年%02d月%02d日', (int)$Y, (int)$m, (int)$d);
    }

    /** ラベル（有/無など） */
    private function yesNo(?string $v): string
    {
        return $v === '有' ? '有' : ($v === '無' ? '無' : '—');
    }

    public function show(int $id)
    {
        /** @var ProBowler $p */
        $p = ProBowler::with(['district','titles' => function($q){ $q->orderBy('year','desc'); }])->findOrFail($id);

        // 基本
        $name    = $p->name_kanji ?? $p->name ?? '';
        $kana    = $p->name_kana ?? '';
        $sex     = $p->sex === 1 ? '男性' : ($p->sex === 2 ? '女性' : '—');
        $licRaw  = $p->license_no ?? '';
        $licNum  = $this->licenseDigits($licRaw);
        $photo   = $this->storageUrl($p->profile_image_public ?: ($p->public_image_path ?: null));
        $district= $p->district->label ?? '—';
        $kibetsu = $p->kibetsu ? ($p->kibetsu.'期') : '—';

        // 公開用生年月日
        $birthPub = $this->formatPublicBirth(
            optional($p->birthdate_public)->format('Y-m-d') ?: (isset($p->birthdate_public) ? (string)$p->birthdate_public : null),
            (bool)$p->birthdate_public_hide_year,
            (bool)$p->birthdate_public_is_private
        );

        // 体格など（公開チェック付き）
        $height = ($p->height_is_public ? ($p->height_cm ? $p->height_cm.' cm' : '—') : '—');
        $weight = ($p->weight_is_public ? ($p->weight_kg ? $p->weight_kg.' kg' : '—') : '—');
        $blood  = ($p->blood_type_is_public ? ($p->blood_type ?: '—') : '—');

        // インストラクター関連
        $instItems = [
            'a_class' => 'A級','b_class' => 'B級','c_class' => 'C級','master' => 'マスターコーチ',
            'coach_4' => 'スポーツ協会認定コーチ4','coach_3' => 'スポーツ協会認定コーチ3','coach_1' => 'スポーツ協会認定コーチ1',
            'kenkou'  => '健康ボウリング教室開講指導員資格','school_license' => 'スクール開講資格',
        ];
        $instructors = [];
        foreach ($instItems as $field => $label) {
            $instructors[] = [
                'label' => $label,
                'status'=> $this->yesNo($p->{$field.'_status'} ?? null),
                'year'  => $p->{$field.'_year'} ?? null,
            ];
        }
        $usbc = $p->usbc_coach ?: '—';

        // SNS / Link
        $sns = [
            'Facebook'  => $p->facebook ?: null,
            'Twitter'   => $p->twitter ?: null,
            'Instagram' => $p->instagram ?: null,
            'Rankseeker'=> $p->rankseeker ?: null,
        ];

        // スポンサー
        $sponsors = [
            ['label'=>'A','name'=>$p->sponsor_a ?? null,'url'=>$p->sponsor_a_url ?? null],
            ['label'=>'B','name'=>$p->sponsor_b ?? null,'url'=>$p->sponsor_b_url ?? null],
            ['label'=>'C','name'=>$p->sponsor_c ?? null,'url'=>$p->sponsor_c_url ?? null],
        ];

        // その他公開項目
        $view = [
            'id'              => $p->id,
            'portrait'        => $photo,
            'name'            => $name,
            'kana'            => $kana,
            'sex'             => $sex,
            'license_no'      => $licNum,
            'district'        => $district,
            'kibetsu'         => $kibetsu,
            'pro_entry_year'  => $p->pro_entry_year ?: '—',
            'birth_public'    => $birthPub,
            'birthplace'      => $p->birthplace ?: '—',
            'height'          => $height,
            'weight'          => $weight,
            'blood'           => $blood,
            'dominant_arm'    => $p->dominant_arm ?: '—',
            'membership_type' => $p->membership_type ?: '—',
            'is_district_leader' => (bool)$p->is_district_leader,
            'organization'    => [
                'name' => $p->organization_name ?: null,
                'url'  => $p->organization_url ?: null,
                'zip'  => $p->public_addr_same_as_org ? ($p->organization_zip ?: null)  : ($p->public_zip ?: null),
                'addr1'=> $p->public_addr_same_as_org ? ($p->organization_addr1 ?: null): ($p->public_addr1 ?: null),
                'addr2'=> $p->public_addr_same_as_org ? ($p->organization_addr2 ?: null): ($p->public_addr2 ?: null),
                'same_as_org' => (bool)$p->public_addr_same_as_org,
            ],
            'a_license_number'=> $p->a_license_number ?: '—',
            'permanent_seed'  => $p->permanent_seed_date ? $p->permanent_seed_date->format('Y-m-d') : '—',
            'hall_of_fame'    => $p->hall_of_fame_date ? $p->hall_of_fame_date->format('Y-m-d') : '—',

            // 自己紹介系
            'hobby'              => $p->hobby ?: null,
            'bowling_history'    => $p->bowling_history ?: null,
            'other_sports'       => $p->other_sports_history ?: null,
            'season_goal'        => $p->season_goal ?: null,
            'coach'              => $p->coach ?: null,
            'equipment_contract' => $p->equipment_contract ?: null,
            'coaching_history'   => $p->coaching_history ?: null,
            'motto'              => $p->motto ?: null,
            'selling_point'      => $p->selling_point ?: null,
            'free_comment'       => $p->free_comment ?: null,
            'jbc_driller_cert'   => $this->yesNo($p->jbc_driller_cert ?? null),

            'sns'            => $sns,
            'sponsors'       => $sponsors,
            'instructors'    => $instructors,
            'usbc_coach'     => $usbc,
            'titles'         => $p->titles ?? collect(),
        ];

        return view('pro_bowlers.public_show', compact('view'));
    }
}
