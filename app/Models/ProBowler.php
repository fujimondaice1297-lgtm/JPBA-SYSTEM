<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProBowlerTraining;

class ProBowler extends Model
{
    protected $table = 'pro_bowlers';

    // app/Models/ProBowler.php
protected $fillable = [
  'license_no','name_kanji','name_kana','sex','district_id','kibetsu',
  'membership_type','license_issue_date','birthdate','birthplace',
  'pro_entry_year','coach','mailing_preference','phone_home','email',
  'qr_code_path','public_image_path','memo',

  'organization_name','organization_zip','organization_addr1','organization_addr2','organization_url',
  'public_zip','public_addr1','public_addr2','public_addr_same_as_org',
  'mailing_zip','mailing_addr1','mailing_addr2','mailing_addr_same_as_org',

  'password_change_status','login_id','mypage_temp_password','height_cm','height_is_public',
  'weight_kg','weight_is_public','blood_type','blood_type_is_public','dominant_arm',
  'a_license_number','hall_of_fame_date','birthdate', 'birthdate_public', 'birthdate_public_hide_year', 
  'birthdate_public_is_private','is_district_leader',

  'jbc_driller_cert','usbc_coach',
  'a_class_status','a_class_year','b_class_status','b_class_year',
  'c_class_status','c_class_year','master_status','master_year',
  'coach_4_status','coach_4_year','coach_3_status','coach_3_year','coach_1_status','coach_1_year',
  'kenkou_status','kenkou_year','school_license_status','school_license_year',

  'hobby','bowling_history','other_sports_history','facebook','twitter','instagram','rankseeker',
  'selling_point','free_comment','motto','equipment_contract','coaching_history',
  'mailing_zip','mailing_addr1','mailing_addr2','mailing_addr_same_as_org',
    'login_id','mypage_temp_password','public_zip','public_addr1','public_addr2','public_addr_same_as_org',

  'sponsor_a','sponsor_a_url','sponsor_b','sponsor_b_url','sponsor_c','sponsor_c_url',
  'association_role','a_license_number','permanent_seed_date','season_goal','motto',
  'organization_name','organization_zip','organization_addr1','organization_addr2','organization_url',
  'public_zip','public_addr1','public_addr2','public_addr_same_as_org','password_change_status',
];

protected $casts = [
  'public_addr_same_as_org' => 'boolean',
  'mailing_addr_same_as_org'=> 'boolean',
  'height_cm' => 'integer',
  'weight_kg' => 'integer',
  'height_is_public' => 'boolean',
  'weight_is_public' => 'boolean',
  'blood_type_is_public' => 'boolean',
  'is_district_leader' => 'boolean',
  'license_issue_date' => 'date',
  'permanent_seed_date' => 'date',
  'mailing_addr_same_as_org' => 'boolean',
  'hall_of_fame_date' => 'date',       // or 'immutable_date'
  'birthdate'         => 'date',
  'birthdate_public'  => 'date',
];

    public $timestamps = true;

    public function district()
    {
        return $this->belongsTo(\App\Models\District::class, 'district_id');
    }

    public function getDistrictLabelAttribute(): ?string
    {
        return $this->district?->label; // districtsテーブルの「label」（日本語）
    }

    public function getGenderAttribute()
    {
        return match ($this->sex) {
            1 => '男性',
            2 => '女性',
            default => '-',
        };
    }

    public function getHasTitleAttribute()
    {
        // withCount('titles') 済なら titles_count を使う。未ロードなら exists() で確認。
        return isset($this->titles_count)
            ? ((int)$this->titles_count > 0)
            : $this->titles()->exists();
    }

    public function getIsDistrictLeaderAttribute()
    {
        return (bool) ($this->attributes['is_district_leader'] ?? false);
    }

    public function getHasSportsCoachLicenseAttribute(): bool
    {
        $vals = [
            $this->attributes['coach_1_status'] ?? null,
            $this->attributes['coach_3_status'] ?? null,
            $this->attributes['coach_4_status'] ?? null,
        ];
        return in_array('有', $vals, true);
    }

    // インストラクター級（A > B > C の最大だけ表示。無ければ '-'）
    public function getInstructorGradeLabelAttribute(): string
    {
        if (($this->attributes['a_class_status'] ?? null) === '有') return 'A級';
        if (($this->attributes['b_class_status'] ?? null) === '有') return 'B級';
        if (($this->attributes['c_class_status'] ?? null) === '有') return 'C級';
        return '-';
    }

    public function getSportsCoachLabelAttribute(): string
    {
        if (($this->attributes['coach_4_status'] ?? null) === '有') return 'コーチ4';
        if (($this->attributes['coach_3_status'] ?? null) === '有') return 'コーチ3';
        if (($this->attributes['coach_1_status'] ?? null) === '有') return 'コーチ1';
        return '-';
    }

    // 任意：どこでも使える表示用アクセサ
    public function getBirthdatePublicForDisplayAttribute(): ?string
    {
        if ($this->birthdate_public_is_private) return null; // 非公表
        if (!$this->birthdate_public) return null;

        $d = \Illuminate\Support\Carbon::parse($this->birthdate_public);
        return $this->birthdate_public_hide_year
            ? $d->format('m/d')           // 年を隠す（例：04/18）
            : $d->format('Y/m/d');        // フル表示（例：1989/04/18）
    }   

    public function titles() {
        return $this->hasMany(\App\Models\ProBowlerTitle::class, 'pro_bowler_id')
                    ->orderByDesc('won_date')->orderByDesc('year');
    }

    public function records()   // ←名前はrecordsでOK（RecordTypeの褒章レコード）
    {
        return $this->hasMany(RecordType::class, 'pro_bowler_id');
    }

    // 一覧やフォームで便利に使える表示用
    public function getTitleCountAttribute(): int {
        // withCount を使うときは $this->titles_count がセットされるのでそちら優先
        return $this->titles_count ?? $this->titles()->count();
    }

    public function trainings()
    {
        return $this->hasMany(\App\Models\ProBowlerTraining::class);
    }

    public function getKibetsuLabelAttribute(): string
    {
        return $this->kibetsu ? ($this->kibetsu . '期') : '-';
    }

    public function latestMandatoryTraining()
    {
        return $this->hasOne(ProBowlerTraining::class, 'pro_bowler_id')
            // 必修だけに絞る（trainingsテーブルのcode=mandatory）
            ->whereHas('training', fn ($q) => $q->where('code', 'mandatory'))
            // 各選手ごとに completed_at が最大の1件を返す
            ->ofMany('completed_at', 'max')   // (= latestOfMany('completed_at') と同義)
            ->withDefault();                  // 無い場合に null の代わりに空オブジェクトを返すなら（任意）
    }

    public function getComplianceStatusAttribute()
    {
        $rec = $this->latestMandatoryTraining()->first();
        if (!$rec) return 'missing';
        if ($rec->expires_at->isPast()) return 'expired';
        if ($rec->expires_at->lte(today()->addDays(60))) return 'expiring_soon';
        return 'compliant';
    }

    public function mandatoryTrainings()
    {
        return $this->hasMany(ProBowlerTraining::class)
            ->where('training_id', function ($q) {
                $q->select('id')->from('trainings')->where('code', 'mandatory')->limit(1);
            })
            ->orderByDesc('completed_at'); // 最新順（Controllerで take(2) しています）
    }

    public function entries() {
        return $this->hasMany(\App\Models\TournamentEntry::class, 'pro_bowler_id');
    }

}
