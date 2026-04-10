<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProBowler extends Model
{
    protected $table = 'pro_bowlers';

    protected $fillable = [
        'license_no',
        'name_kanji',
        'name_kana',
        'sex',
        'district_id',
        'acquire_date',
        'is_active',
        'is_visible',
        'coach_qualification',
        'kibetsu',
        'membership_type',
        'member_class',
        'can_enter_official_tournament',
        'license_issue_date',
        'phone_home',
        'has_title',
        'is_district_leader',
        'has_sports_coach_license',
        'sports_coach_name',
        'birthdate',
        'birthplace',
        'height_cm',
        'weight_kg',
        'blood_type',
        'home_zip',
        'home_address',
        'work_zip',
        'work_address',
        'organization_url',
        'phone_work',
        'phone_mobile',
        'fax_number',
        'email',
        'image_path',
        'public_image_path',
        'qr_code_path',
        'mailing_preference',
        'pro_entry_year',
        'hobby',
        'bowling_history',
        'other_sports_history',
        'season_goal',
        'coach',
        'selling_point',
        'free_comment',
        'facebook',
        'twitter',
        'instagram',
        'rankseeker',
        'jbc_driller_cert',
        'a_license_date',
        'permanent_seed_date',
        'hall_of_fame_date',
        'birthdate_public',
        'memo',
        'usbc_coach',
        'a_class_status',
        'a_class_year',
        'b_class_status',
        'b_class_year',
        'c_class_status',
        'c_class_year',
        'master_status',
        'master_year',
        'coach_4_status',
        'coach_4_year',
        'coach_3_status',
        'coach_3_year',
        'coach_1_status',
        'coach_1_year',
        'kenkou_status',
        'kenkou_year',
        'school_license_status',
        'school_license_year',
        'titles_count',
        'perfect_count',
        'seven_ten_count',
        'eight_hundred_count',
        'award_total_count',
        'organization_name',
        'organization_zip',
        'organization_addr1',
        'organization_addr2',
        'public_zip',
        'public_addr1',
        'public_addr2',
        'public_addr_same_as_org',
        'mailing_zip',
        'mailing_addr1',
        'mailing_addr2',
        'mailing_addr_same_as_org',
        'password_change_status',
        'login_id',
        'mypage_temp_password',
        'height_is_public',
        'weight_is_public',
        'blood_type_is_public',
        'dominant_arm',
        'motto',
        'equipment_contract',
        'coaching_history',
        'sponsor_a',
        'sponsor_a_url',
        'sponsor_b',
        'sponsor_b_url',
        'sponsor_c',
        'sponsor_c_url',
        'association_role',
        'a_license_number',
        'birthdate_public_hide_year',
        'birthdate_public_is_private',
    ];

    protected $casts = [
        'sex' => 'integer',
        'district_id' => 'integer',
        'kibetsu' => 'integer',
        'mailing_preference' => 'integer',
        'pro_entry_year' => 'integer',
        'titles_count' => 'integer',
        'perfect_count' => 'integer',
        'seven_ten_count' => 'integer',
        'eight_hundred_count' => 'integer',
        'award_total_count' => 'integer',
        'password_change_status' => 'integer',
        'height_cm' => 'integer',
        'weight_kg' => 'integer',
        'a_license_number' => 'integer',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'coach_qualification' => 'boolean',
        'has_title' => 'boolean',
        'is_district_leader' => 'boolean',
        'has_sports_coach_license' => 'boolean',
        'can_enter_official_tournament' => 'boolean',
        'public_addr_same_as_org' => 'boolean',
        'mailing_addr_same_as_org' => 'boolean',
        'height_is_public' => 'boolean',
        'weight_is_public' => 'boolean',
        'blood_type_is_public' => 'boolean',
        'birthdate_public_hide_year' => 'boolean',
        'birthdate_public_is_private' => 'boolean',
        'acquire_date' => 'date',
        'license_issue_date' => 'date',
        'birthdate' => 'date',
        'a_license_date' => 'date',
        'permanent_seed_date' => 'date',
        'hall_of_fame_date' => 'date',
        'birthdate_public' => 'date',
    ];

    public function district()
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function instructorRegistries()
    {
        return $this->hasMany(InstructorRegistry::class, 'pro_bowler_id');
    }

    public function currentInstructorRegistry()
    {
        return $this->hasOne(InstructorRegistry::class, 'pro_bowler_id')
            ->where('is_current', true)
            ->latestOfMany();
    }

    public function titles()
    {
        return $this->hasMany(ProBowlerTitle::class, 'pro_bowler_id')
            ->orderByDesc('won_date')
            ->orderByDesc('year');
    }

    public function records()
    {
        return $this->hasMany(RecordType::class, 'pro_bowler_id');
    }

    public function trainings()
    {
        return $this->hasMany(ProBowlerTraining::class, 'pro_bowler_id');
    }

    public function latestMandatoryTraining()
    {
        return $this->hasOne(ProBowlerTraining::class, 'pro_bowler_id')
            ->whereHas('training', fn ($q) => $q->where('code', 'mandatory'))
            ->ofMany('completed_at', 'max')
            ->withDefault();
    }

    public function mandatoryTrainings()
    {
        return $this->hasMany(ProBowlerTraining::class, 'pro_bowler_id')
            ->where('training_id', function ($q) {
                $q->select('id')
                    ->from('trainings')
                    ->where('code', 'mandatory')
                    ->limit(1);
            })
            ->orderByDesc('completed_at');
    }

    public function entries()
    {
        return $this->hasMany(TournamentEntry::class, 'pro_bowler_id');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members', 'pro_bowler_id', 'group_id')
            ->withPivot(['source', 'assigned_at', 'expires_at'])
            ->withTimestamps();
    }

    public function getDistrictLabelAttribute(): ?string
    {
        return $this->district?->label;
    }

    public function getGenderAttribute(): string
    {
        return match ((int) $this->sex) {
            1 => '男性',
            2 => '女性',
            default => '-',
        };
    }

    public function getHasTitleAttribute($value): bool
    {
        if ($value !== null) {
            return (bool) $value;
        }

        return isset($this->titles_count)
            ? ((int) $this->titles_count > 0)
            : $this->titles()->exists();
    }

    public function getHasSportsCoachLicenseAttribute($value): bool
    {
        if ($value !== null) {
            return (bool) $value;
        }

        $vals = [
            $this->attributes['coach_1_status'] ?? null,
            $this->attributes['coach_3_status'] ?? null,
            $this->attributes['coach_4_status'] ?? null,
        ];

        return in_array('有', $vals, true);
    }

    public function getInstructorGradeLabelAttribute(): string
    {
        if (($this->attributes['a_class_status'] ?? null) === '有') {
            return 'A級';
        }
        if (($this->attributes['b_class_status'] ?? null) === '有') {
            return 'B級';
        }
        if (($this->attributes['c_class_status'] ?? null) === '有') {
            return 'C級';
        }

        return '-';
    }

    public function getSportsCoachLabelAttribute(): string
    {
        if (($this->attributes['coach_4_status'] ?? null) === '有') {
            return 'コーチ4';
        }
        if (($this->attributes['coach_3_status'] ?? null) === '有') {
            return 'コーチ3';
        }
        if (($this->attributes['coach_1_status'] ?? null) === '有') {
            return 'コーチ1';
        }

        return '-';
    }

    public function getMemberClassLabelAttribute(): string
    {
        return match ($this->member_class) {
            'pro_instructor'      => 'プロインストラクター',
            'player'              => 'プロボウラー',
            'honorary_or_overseas'=> '名誉プロ・海外',
            default               => '-',
        };
    }

    public function getOfficialTournamentEligibilityLabelAttribute(): string
    {
        return $this->can_enter_official_tournament ? '出場可' : '対象外';
    }

    public function getCurrentInstructorSyncStateLabelAttribute(): string
    {
        $registry = $this->relationLoaded('currentInstructorRegistry')
            ? $this->getRelation('currentInstructorRegistry')
            : $this->currentInstructorRegistry()->first();

        if ($registry) {
            return 'currentあり';
        }

        $hasHistory = $this->relationLoaded('instructorRegistries')
            ? $this->getRelation('instructorRegistries')->isNotEmpty()
            : $this->instructorRegistries()->exists();

        return $hasHistory ? 'historyのみ' : '未同期';
    }

    public function getCurrentInstructorTypeLabelAttribute(): string
    {
        $registry = $this->relationLoaded('currentInstructorRegistry')
            ? $this->getRelation('currentInstructorRegistry')
            : $this->currentInstructorRegistry()->first();

        return $registry?->type_label ?? '-';
    }

    public function getCurrentInstructorSourceLabelAttribute(): string
    {
        $registry = $this->relationLoaded('currentInstructorRegistry')
            ? $this->getRelation('currentInstructorRegistry')
            : $this->currentInstructorRegistry()->first();

        return match ($registry?->source_type) {
            'auth_instructor_csv' => '認定CSV',
            'pro_bowler_csv'      => 'プロCSV',
            'legacy_instructors'  => '旧instructors',
            'manual'              => '手動登録',
            null                  => '-',
            default               => (string) $registry->source_type,
        };
    }

    public function getCurrentInstructorRenewalStatusLabelAttribute(): string
    {
        $registry = $this->relationLoaded('currentInstructorRegistry')
            ? $this->getRelation('currentInstructorRegistry')
            : $this->currentInstructorRegistry()->first();

        return $registry?->renewal_status_label ?? '-';
    }

    public function getBirthdatePublicForDisplayAttribute(): ?string
    {
        if ($this->birthdate_public_is_private || !$this->birthdate_public) {
            return null;
        }

        $d = Carbon::parse($this->birthdate_public);

        return $this->birthdate_public_hide_year
            ? $d->format('m/d')
            : $d->format('Y/m/d');
    }

    public function getComplianceStatusAttribute(): string
    {
        $rec = $this->latestMandatoryTraining()->first();
        if (!$rec) {
            return 'missing';
        }

        $expiresAt = $rec->expires_at;
        if (!$expiresAt) {
            return 'missing';
        }

        if ($expiresAt->isPast()) {
            return 'expired';
        }
        if ($expiresAt->lte(today()->addDays(60))) {
            return 'expiring_soon';
        }

        return 'compliant';
    }

    public function getTitleCountAttribute(): int
    {
        return isset($this->attributes['titles_count'])
            ? (int) $this->attributes['titles_count']
            : $this->titles()->count();
    }

    public function getKibetsuLabelAttribute(): string
    {
        return $this->kibetsu ? ($this->kibetsu . '期') : '-';
    }
}