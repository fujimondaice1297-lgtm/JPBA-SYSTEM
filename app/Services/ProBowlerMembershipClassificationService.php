<?php

namespace App\Services;

use App\Models\ProBowler;
use Illuminate\Support\Facades\DB;

final class ProBowlerMembershipClassificationService
{
    public const FIRST_SEED = '第１シード';

    public const SECOND_SEED = '第２シード';

    public const TOURNAMENT_PRO = 'トーナメントプロ';

    public const TRAINING_ATTENDEE = '講習会出席者';

    public const OVERSEAS_PRO = '海外プロ';

    public const OTHER = 'その他';

    /** @var list<string> */
    private const TERMINAL_TYPES = ['死亡', '除名', '退会届', '退会員'];

    /** @var list<string> */
    private const INSTRUCTOR_TYPES = ['プロインストラクター', '認定プロインストラクター'];

    public function __construct(private readonly TrainingComplianceService $trainingCompliance)
    {
    }

    /**
     * @return array{
     *   seed_by_id:array<int,array<string,mixed>>,
     *   seed_by_license:array<string,array<string,mixed>>,
     *   participant_ids:array<int,true>,
     *   participant_licenses:array<string,true>
     * }
     */
    public function contextForYear(int $year): array
    {
        $seedById = [];
        $seedByLicense = [];

        $seedRows = DB::table('pro_bowler_seed_list_players as player')
            ->join('pro_bowler_seed_lists as list', 'list.id', '=', 'player.seed_list_id')
            ->where('list.seed_year', $year)
            ->where('list.seed_list_type', 'tournament_seed')
            ->where('list.is_active', true)
            ->where('player.is_active', true)
            ->where('player.seed_category', ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED)
            ->select([
                'player.pro_bowler_id',
                'player.license_no',
                'player.seed_rank',
                'list.gender',
            ])
            ->orderBy('player.seed_rank')
            ->get();

        foreach ($seedRows as $row) {
            $signal = [
                'gender' => strtoupper((string) $row->gender),
                'rank' => (int) $row->seed_rank,
            ];

            if ($row->pro_bowler_id !== null) {
                $seedById[(int) $row->pro_bowler_id] ??= $signal;
            }

            $license = $this->normalizeLicenseNo($row->license_no);
            if ($license !== null) {
                $seedByLicense[$license] ??= $signal;
            }
        }

        $participantIds = [];
        $participantLicenses = [];
        $resultRows = DB::table('tournament_results as result')
            ->join('tournaments as tournament', 'tournament.id', '=', 'result.tournament_id')
            ->where('tournament.year', $year)
            ->where('tournament.official_type', 'official')
            ->select(['result.pro_bowler_id', 'result.pro_bowler_license_no'])
            ->distinct()
            ->get();

        foreach ($resultRows as $row) {
            if ($row->pro_bowler_id !== null) {
                $participantIds[(int) $row->pro_bowler_id] = true;
            }

            $license = $this->normalizeLicenseNo($row->pro_bowler_license_no);
            if ($license !== null) {
                $participantLicenses[$license] = true;
            }
        }

        return [
            'seed_by_id' => $seedById,
            'seed_by_license' => $seedByLicense,
            'participant_ids' => $participantIds,
            'participant_licenses' => $participantLicenses,
        ];
    }

    /**
     * @param  array<string,mixed>|null  $context
     * @param  array{training_allowed?:bool}|null  $signals
     * @return array<string,mixed>
     */
    public function decide(
        ProBowler $bowler,
        int $year,
        ?array $context = null,
        ?array $signals = null,
    ): array {
        $context ??= $this->contextForYear($year);
        $currentType = trim((string) $bowler->membership_type);

        if (! (bool) $bowler->is_active || in_array($currentType, self::TERMINAL_TYPES, true)) {
            $type = match ($currentType) {
                '死亡', '除名', '退会届' => $currentType,
                '退会員' => '退会届',
                default => self::OTHER,
            };

            return $this->decision($type, 'other', false, '非アクティブ会員', $year, false, false);
        }

        if (
            $bowler->member_class === 'pro_instructor'
            || in_array($currentType, self::INSTRUCTOR_TYPES, true)
            || $this->isTeachingProLicense($bowler->license_no)
        ) {
            $type = $currentType === '認定プロインストラクター'
                ? '認定プロインストラクター'
                : 'プロインストラクター';

            return $this->decision($type, 'pro_instructor', false, 'インストラクター区分', $year, false, false);
        }

        $license = $this->normalizeLicenseNo($bowler->license_no);
        $seed = $context['seed_by_id'][(int) $bowler->id]
            ?? ($license !== null ? ($context['seed_by_license'][$license] ?? null) : null);

        if (is_array($seed)) {
            $gender = strtoupper((string) ($seed['gender'] ?? ''));
            $rank = (int) ($seed['rank'] ?? 0);
            $isSecondSeed = $gender === 'F' && $rank > 18;
            $type = $isSecondSeed ? self::SECOND_SEED : self::FIRST_SEED;
            $reason = $gender === 'F'
                ? sprintf('%d年度女子シード第%d位', $year, $rank)
                : sprintf('%d年度男子シード第%d位', $year, $rank);

            return $this->decision($type, 'player', true, $reason, $year, true, false);
        }

        $trainingAllowed = array_key_exists('training_allowed', $signals ?? [])
            ? (bool) $signals['training_allowed']
            : (bool) ($this->trainingCompliance->entryDecision($bowler)['allowed'] ?? false);
        $participated = isset($context['participant_ids'][(int) $bowler->id])
            || ($license !== null && isset($context['participant_licenses'][$license]));

        if ($trainingAllowed) {
            $type = $participated ? self::TOURNAMENT_PRO : self::TRAINING_ATTENDEE;
            $reason = $participated
                ? sprintf('TP講習有効・%d年度公式戦出場履歴あり', $year)
                : sprintf('TP講習有効・%d年度公式戦出場履歴なし', $year);

            return $this->decision($type, 'player', true, $reason, $year, false, $participated);
        }

        if ($this->isOverseasPro($bowler)) {
            return $this->decision(self::OVERSEAS_PRO, 'honorary_or_overseas', false, '海外ライセンスまたは外国語表記', $year, false, $participated);
        }

        return $this->decision(self::OTHER, 'other', false, '他の区分条件に該当なし', $year, false, $participated);
    }

    /** @param array<string,mixed>|null $context */
    public function syncBowler(
        ProBowler $bowler,
        int $year,
        ?array $context = null,
        bool $dryRun = false,
        bool $refreshTraining = false,
    ): array {
        if ($refreshTraining && (bool) $bowler->is_active && $bowler->member_class !== 'pro_instructor') {
            $this->trainingCompliance->syncBowler($bowler);
            $bowler->refresh();
        }

        $decision = $this->decide($bowler, $year, $context);
        $changes = [];
        foreach ([
            'membership_type' => $decision['membership_type'],
            'member_class' => $decision['member_class'],
            'can_enter_official_tournament' => $decision['can_enter_official_tournament'],
        ] as $column => $value) {
            if ($bowler->getAttribute($column) !== $value) {
                $changes[$column] = [
                    'from' => $bowler->getAttribute($column),
                    'to' => $value,
                ];
            }
        }

        if (! $dryRun && $changes !== []) {
            $this->ensureMembershipStatus((string) $decision['membership_type']);
            $bowler->forceFill([
                'membership_type' => $decision['membership_type'],
                'member_class' => $decision['member_class'],
                'can_enter_official_tournament' => $decision['can_enter_official_tournament'],
            ])->save();
        }

        return $decision + ['changes' => $changes, 'changed' => $changes !== []];
    }

    /** @return array<string,mixed> */
    public function syncAll(int $year, bool $dryRun = false): array
    {
        $context = $this->contextForYear($year);
        $summary = [
            'year' => $year,
            'processed' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'counts' => [],
            'change_samples' => [],
        ];

        ProBowler::query()
            ->orderBy('id')
            ->chunkById(100, function ($bowlers) use ($year, $context, $dryRun, &$summary): void {
                foreach ($bowlers as $bowler) {
                    $decision = $this->syncBowler(
                        $bowler,
                        $year,
                        $context,
                        $dryRun,
                        refreshTraining: ! $dryRun,
                    );

                    $summary['processed']++;
                    $summary['counts'][$decision['membership_type']] =
                        ($summary['counts'][$decision['membership_type']] ?? 0) + 1;

                    if ($decision['changed']) {
                        $summary['changed']++;
                        if (count($summary['change_samples']) < 30) {
                            $summary['change_samples'][] = [
                                'id' => $bowler->id,
                                'license_no' => $bowler->license_no,
                                'name' => $bowler->name_kanji,
                                'reason' => $decision['reason'],
                                'changes' => $decision['changes'],
                            ];
                        }
                    } else {
                        $summary['unchanged']++;
                    }
                }
            });

        ksort($summary['counts']);

        return $summary;
    }

    public function isOverseasPro(ProBowler $bowler): bool
    {
        $license = $this->normalizeLicenseNo($bowler->license_no) ?? '';
        if (preg_match('/^[MF]0*[KP][0-9]+$/', $license) === 1) {
            return true;
        }

        $name = trim((string) $bowler->name_kanji);
        if ($name === '') {
            return false;
        }

        if (preg_match('/[A-Za-z\x{AC00}-\x{D7AF}]/u', $name) === 1) {
            return true;
        }

        $hasKanjiOrHiragana = preg_match('/[\x{3400}-\x{9FFF}\x{3040}-\x{309F}]/u', $name) === 1;
        $hasKatakana = preg_match('/[\x{30A0}-\x{30FF}]/u', $name) === 1;

        return ! $hasKanjiOrHiragana && $hasKatakana;
    }

    /** @return array<string,mixed> */
    private function decision(
        string $membershipType,
        string $memberClass,
        bool $canEnterOfficialTournament,
        string $reason,
        int $year,
        bool $isSeed,
        bool $participated,
    ): array {
        return [
            'membership_type' => $membershipType,
            'member_class' => $memberClass,
            'can_enter_official_tournament' => $canEnterOfficialTournament,
            'reason' => $reason,
            'year' => $year,
            'is_seed' => $isSeed,
            'participated' => $participated,
        ];
    }

    private function ensureMembershipStatus(string $name): void
    {
        DB::table('kaiin_status')->insertOrIgnore([
            'name' => $name,
            'reg_date' => now(),
            'del_flg' => false,
            'update_date' => now(),
            'created_by' => 'system',
            'updated_by' => 'system',
            'is_retired' => in_array($name, self::TERMINAL_TYPES, true),
        ]);
    }

    private function normalizeLicenseNo(mixed $licenseNo): ?string
    {
        $value = strtoupper(trim((string) $licenseNo));

        return $value === '' ? null : $value;
    }

    private function isTeachingProLicense(mixed $licenseNo): bool
    {
        $value = $this->normalizeLicenseNo($licenseNo) ?? '';

        return preg_match('/^[MF]0*T[0-9]+$/', $value) === 1;
    }
}
