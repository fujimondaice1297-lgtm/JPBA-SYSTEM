<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SeedStWinter2026CResultCommand extends Command
{
    protected $signature = 'jpba:seed-st-winter-2026-c';

    protected $description = 'Seed JPBA Season Trial 2026 Winter Series C venue official result test data.';

    public function handle(): int
    {
        try {
            DB::transaction(function (): void {
                $now = now();

                $tournamentId = $this->upsertTournament($now);

                $this->line('Tournament ID: ' . $tournamentId);

                $this->upsertStepPointDistributions($tournamentId, $now, 18);

                DB::table('game_scores')
                    ->where('tournament_id', $tournamentId)
                    ->whereIn('stage', ['予選', '準決勝'])
                    ->delete();

                $this->deleteCurrentSnapshots($tournamentId);

                DB::table('tournament_results')
                    ->where('tournament_id', $tournamentId)
                    ->delete();

                $prelimRows = $this->prelimRows();
                $semiRows = $this->semiRows();
                $finalRows = $this->finalRows();

                $gameScoreInserts = [];

                foreach ($prelimRows as $row) {
                    $licenseNo = $this->normalizeMaleLicense($row['license']);
                    $proBowlerId = $this->findProBowlerId($licenseNo);

                    foreach ($row['scores'] as $index => $score) {
                        $gameScoreInserts[] = [
                            'tournament_id' => $tournamentId,
                            'stage' => '予選',
                            'license_number' => $licenseNo,
                            'name' => $row['name'],
                            'entry_number' => null,
                            'game_number' => $index + 1,
                            'score' => $score,
                            'shift' => null,
                            'gender' => 'M',
                            'pro_bowler_id' => $proBowlerId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach ($semiRows as $row) {
                    $licenseNo = $this->normalizeMaleLicense($row['license']);
                    $proBowlerId = $this->findProBowlerId($licenseNo);

                    foreach ($row['scores'] as $index => $score) {
                        $gameScoreInserts[] = [
                            'tournament_id' => $tournamentId,
                            'stage' => '準決勝',
                            'license_number' => $licenseNo,
                            'name' => $row['name'],
                            'entry_number' => null,
                            'game_number' => $index + 1,
                            'score' => $score,
                            'shift' => null,
                            'gender' => 'M',
                            'pro_bowler_id' => $proBowlerId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($gameScoreInserts, 500) as $chunk) {
                    DB::table('game_scores')->insert($chunk);
                }

                $this->insertResultSnapshots($tournamentId, $prelimRows, $semiRows, $now);

                $resultInserts = [];

                foreach ($finalRows as $row) {
                    $licenseNo = $this->normalizeMaleLicense($row['license']);
                    $proBowlerId = $this->findProBowlerId($licenseNo);

                    $result = [
                        'pro_bowler_license_no' => $licenseNo,
                        'tournament_id' => $tournamentId,
                        'ranking' => $row['ranking'],
                        'points' => $row['points'],
                        'total_pin' => $row['total_pin'],
                        'games' => 12,
                        'average' => $row['average'],
                        'prize_money' => $row['prize_money'],
                        'ranking_year' => 2026,
                        'amateur_name' => $proBowlerId ? null : $row['name'],
                        'pro_bowler_id' => $proBowlerId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (Schema::hasColumn('tournament_results', 'affiliation_display')) {
                        $result['affiliation_display'] = $this->buildAffiliationDisplay($licenseNo, $row['affiliation'] ?? null);
                    }

                    if (Schema::hasColumn('tournament_results', 'award_points')) {
                        $result['award_points'] = $row['award_points'] ?? null;
                    }

                    if (Schema::hasColumn('tournament_results', 'step_points')) {
                        $result['step_points'] = $row['step_points'] ?? null;
                    }

                    $resultInserts[] = $result;
                }

                DB::table('tournament_results')->insert($resultInserts);

                $this->info('Seed completed.');
                $this->line('予選 game_scores: ' . (count($prelimRows) * 8));
                $this->line('準決勝 game_scores: ' . (count($semiRows) * 4));
                $this->line('tournament_results: ' . count($resultInserts));
                $this->line('snapshots: prelim_total / semifinal_total');
                $this->line('PDF URL: /tournaments/' . $tournamentId . '/results/pdf?reload=st2026c');
            });

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function upsertTournament($now): int
    {
        $name = 'メリーランドカップ';
        $venueName = '狐ヶ崎ヤングランドボウル';
        $startDate = '2026-01-28';

        $payload = [
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $startDate,
            'venue_name' => $venueName,
            'venue_address' => '〒424-0871 静岡県静岡市清水区上原1-6-16 イオン清水店3F',
            'venue_tel' => '054(345)4161',
            'venue_fax' => '054(345)5260',
            'host' => '（公社）日本プロボウリング協会',
            'authorized_by' => '（公社）日本プロボウリング協会',
            'supervisor' => '会場該当地区（静岡地区）及び事務局',
            'year' => 2026,
            'gender' => 'M',
            'official_type' => 'official',
            'title_category' => 'season_trial',
            'result_flow_type' => 'prelim_to_semifinal_to_shootout_to_final',
            'shootout_qualifier_count' => 8,
            'shootout_seed_source_result_code' => 'semifinal_total',
            'shootout_format' => 'standard_8',
            'shootout_settings' => json_encode([
                'stage_progress' => [
                    'prelim_player_count' => 34,
                    'prelim_game_count' => 8,
                    'prelim_qualifier_count' => 18,
                    'semifinal_game_count' => 4,
                    'semifinal_total_game_count' => 12,
                    'semifinal_qualifier_count' => 8,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ];

        $existingId = DB::table('tournaments')
            ->where('name', $name)
            ->where('year', 2026)
            ->where('venue_name', $venueName)
            ->value('id');

        if ($existingId) {
            DB::table('tournaments')
                ->where('id', $existingId)
                ->update($payload);

            return (int) $existingId;
        }

        $payload['created_at'] = $now;

        return (int) DB::table('tournaments')->insertGetId($payload);
    }

    private function normalizeMaleLicense(string $license): string
    {
        $digits = preg_replace('/\D+/', '', $license) ?: $license;

        return 'M' . str_pad($digits, 8, '0', STR_PAD_LEFT);
    }

    private function findProBowlerId(string $licenseNo): ?int
    {
        $id = DB::table('pro_bowlers')
            ->where('license_no', $licenseNo)
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        $digits = preg_replace('/\D+/', '', $licenseNo) ?: '';

        if ($digits === '') {
            return null;
        }

        $id = DB::table('pro_bowlers')
            ->where('license_no', 'like', '%' . ltrim($digits, '0'))
            ->orWhere('license_no', 'like', '%' . substr($digits, -4))
            ->value('id');

        return $id ? (int) $id : null;
    }

private function deleteCurrentSnapshots(int $tournamentId): void
{
    $snapshotIds = DB::table('tournament_result_snapshots')
        ->where('tournament_id', $tournamentId)
        ->whereIn('result_code', ['prelim_total', 'semifinal_total'])
        ->pluck('id');

    if ($snapshotIds->isNotEmpty()) {
        DB::table('tournament_result_snapshot_rows')
            ->whereIn('snapshot_id', $snapshotIds->all())
            ->delete();

        DB::table('tournament_result_snapshots')
            ->whereIn('id', $snapshotIds->all())
            ->delete();
    }
}

private function insertResultSnapshots(int $tournamentId, array $prelimRows, array $semiRows, $now): void
{
    $prelimParticipantMeta = $this->participantMetaByLicense($prelimRows);
    $semiParticipantMeta = $this->participantMetaByLicense($semiRows);

    $prelimSnapshotId = $this->insertSnapshotHeader(
        $tournamentId,
        'prelim_total',
        '予選8Gトータルピン成績',
        '予選',
        8,
        0,
        [
            'source_sets' => [['stage' => '予選', 'from_game' => 1, 'to_game' => 8, 'role' => 'scratch']],
            'participant_meta' => $prelimParticipantMeta,
        ],
        $now
    );

    $prelimInserts = [];
    foreach ($prelimRows as $row) {
        $licenseNo = $this->normalizeMaleLicense($row['license']);
        $proBowlerId = $this->findProBowlerId($licenseNo);
        $bowler = $this->proBowlerByLicense($licenseNo);

        $prelimInserts[] = $this->snapshotRowPayload(
            $prelimSnapshotId,
            $row['rank'],
            $proBowlerId,
            $licenseNo,
            $bowler->name_kanji ?? str_replace(' ', '', $row['name']),
            0,
            $row['total'],
            $row['total'],
            8,
            $row['average'],
            null,
            null,
            $now
        );
    }

    foreach (array_chunk($prelimInserts, 500) as $chunk) {
        DB::table('tournament_result_snapshot_rows')->insert($chunk);
    }

    $semiSnapshotId = $this->insertSnapshotHeader(
        $tournamentId,
        'semifinal_total',
        '準決勝4G・通算12Gトータルピン成績',
        '準決勝',
        12,
        8,
        [
            'source_sets' => [
                ['stage' => '予選', 'from_game' => 1, 'to_game' => 8, 'role' => 'carry'],
                ['stage' => '準決勝', 'from_game' => 1, 'to_game' => 4, 'role' => 'scratch'],
            ],
            'participant_meta' => $semiParticipantMeta,
        ],
        $now
    );

    $semiInserts = [];
    foreach ($semiRows as $row) {
        $licenseNo = $this->normalizeMaleLicense($row['license']);
        $proBowlerId = $this->findProBowlerId($licenseNo);
        $bowler = $this->proBowlerByLicense($licenseNo);
        $stepPoints = $this->stepPointsByRank((int) $row['rank'], count($semiRows));

        $semiInserts[] = $this->snapshotRowPayload(
            $semiSnapshotId,
            $row['rank'],
            $proBowlerId,
            $licenseNo,
            $bowler->name_kanji ?? str_replace(' ', '', $row['name']),
            $row['semi_total'],
            $row['prelim_total'],
            $row['total_12g'],
            12,
            $row['average_12g'],
            $stepPoints,
            null,
            $now
        );
    }

    foreach (array_chunk($semiInserts, 500) as $chunk) {
        DB::table('tournament_result_snapshot_rows')->insert($chunk);
    }
}

private function insertSnapshotHeader(
    int $tournamentId,
    string $resultCode,
    string $resultName,
    string $stageName,
    int $gamesCount,
    int $carryGameCount,
    array $calculationDefinition,
    $now
): int {
    return (int) DB::table('tournament_result_snapshots')->insertGetId([
        'tournament_id' => $tournamentId,
        'result_code' => $resultCode,
        'result_name' => $resultName,
        'result_type' => 'total_pin',
        'stage_name' => $stageName,
        'gender' => null,
        'shift' => null,
        'games_count' => $gamesCount,
        'carry_game_count' => $carryGameCount,
        'carry_stage_names' => $carryGameCount > 0 ? json_encode(['予選'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'calculation_definition' => json_encode($calculationDefinition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'reflected_at' => $now,
        'reflected_by' => null,
        'is_final' => false,
        'is_published' => true,
        'is_current' => true,
        'notes' => '公式PDF実働テスト用Seedで投入',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

private function snapshotRowPayload(
    int $snapshotId,
    int $ranking,
    ?int $proBowlerId,
    string $licenseNo,
    string $displayName,
    int $scratchPin,
    int $carryPin,
    int $totalPin,
    int $games,
    float $average,
    ?int $points,
    ?int $prizeMoney,
    $now
): array {
    return [
        'snapshot_id' => $snapshotId,
        'ranking' => $ranking,
        'pro_bowler_id' => $proBowlerId,
        'pro_bowler_license_no' => $licenseNo,
        'amateur_name' => $proBowlerId ? null : $displayName,
        'display_name' => $displayName,
        'gender' => 'M',
        'shift' => null,
        'entry_number' => null,
        'scratch_pin' => $scratchPin,
        'carry_pin' => $carryPin,
        'total_pin' => $totalPin,
        'games' => $games,
        'average' => $average,
        'tie_break_value' => null,
        'points' => $points,
        'prize_money' => $prizeMoney,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

private function proBowlerByLicense(string $licenseNo): ?object
{
    $bowler = DB::table('pro_bowlers')
        ->where('license_no', $licenseNo)
        ->first();

    if ($bowler) {
        return $bowler;
    }

    $digits = preg_replace('/\D+/', '', $licenseNo) ?: '';
    if ($digits === '') {
        return null;
    }

    return DB::table('pro_bowlers')
        ->where('license_no', 'like', '%' . ltrim($digits, '0'))
        ->orWhere('license_no', 'like', '%' . substr($digits, -4))
        ->first();
}

private function participantMetaByLicense(array $rows): array
{
    $meta = [];

    foreach ($rows as $row) {
        $licenseNo = $this->normalizeMaleLicense((string) ($row['license'] ?? ''));

        $meta[$licenseNo] = [
            'period' => isset($row['period']) ? (string) $row['period'] : null,
            'arm' => isset($row['arm']) ? (string) $row['arm'] : null,
            'affiliation' => $this->buildAffiliationDisplay($licenseNo, $row['affiliation'] ?? null),
        ];
    }

    return $meta;
}

private function buildAffiliationDisplay(string $licenseNo, ?string $fallback = null): ?string
{
    $bowler = $this->proBowlerByLicense($licenseNo);
    $parts = [];

    if ($bowler) {
        $organization = trim((string) ($bowler->organization_name ?? ''));
        $equipment = trim((string) ($bowler->equipment_contract ?? ''));

        if ($organization !== '') {
            $parts[] = $organization;
        }

        if ($equipment !== '') {
            $parts[] = $equipment;
        }
    }

    if (!empty($parts)) {
        return implode('/', $parts);
    }

    $fallback = trim((string) $fallback);

    return $fallback !== '' ? $fallback : null;
}

private function upsertStepPointDistributions(int $tournamentId, $now, int $qualifierCount): void
{
    if (!Schema::hasTable('point_distributions')) {
        return;
    }

    DB::table('point_distributions')
        ->where('tournament_id', $tournamentId)
        ->whereBetween('rank', [1, $qualifierCount])
        ->delete();

    $rows = [];

    for ($rank = 1; $rank <= $qualifierCount; $rank++) {
        $row = [
            'tournament_id' => $tournamentId,
            'rank' => $rank,
            'points' => $this->stepPointsByRank($rank, $qualifierCount),
            'pattern_id' => null,
        ];

        if (Schema::hasColumn('point_distributions', 'created_at')) {
            $row['created_at'] = $now;
        }

        if (Schema::hasColumn('point_distributions', 'updated_at')) {
            $row['updated_at'] = $now;
        }

        $rows[] = $row;
    }

    DB::table('point_distributions')->insert($rows);
}

private function stepPointsByRank(int $rank, int $qualifierCount): ?int
{
    if ($rank <= 0 || $rank > $qualifierCount) {
        return null;
    }

    return $qualifierCount - $rank + 1;
}

    private function prelimRows(): array
    {
        return [
            ['rank' => 1, 'license' => '1443', 'name' => '藤永 北斗', 'period' => 61, 'arm' => '左', 'scores' => [300, 266, 236, 280, 258, 204, 265, 280], 'total' => 2089, 'average' => 261.12],
            ['rank' => 2, 'license' => '1384', 'name' => '水野 耕佑', 'period' => 56, 'arm' => '右', 'scores' => [195, 255, 258, 238, 224, 210, 247, 225], 'total' => 1852, 'average' => 231.50],
            ['rank' => 3, 'license' => '1448', 'name' => '木村 謙太', 'period' => 61, 'arm' => '右', 'scores' => [205, 247, 215, 269, 225, 218, 223, 245], 'total' => 1847, 'average' => 230.87],
            ['rank' => 4, 'license' => '1307', 'name' => '播摩 友和', 'period' => 52, 'arm' => '右', 'scores' => [221, 255, 214, 244, 207, 244, 223, 222], 'total' => 1830, 'average' => 228.75],
            ['rank' => 5, 'license' => '1023', 'name' => '岡野 秀幸', 'period' => 42, 'arm' => '右', 'scores' => [213, 226, 268, 234, 206, 233, 236, 214], 'total' => 1830, 'average' => 228.75],
            ['rank' => 6, 'license' => '1394', 'name' => '藤村 隆史', 'period' => 57, 'arm' => '右', 'scores' => [221, 259, 237, 179, 216, 239, 235, 230], 'total' => 1816, 'average' => 227.00],
            ['rank' => 7, 'license' => '1452', 'name' => '内藤 広人', 'period' => 61, 'arm' => '右', 'scores' => [258, 215, 239, 204, 172, 247, 257, 204], 'total' => 1796, 'average' => 224.50],
            ['rank' => 8, 'license' => '1424', 'name' => '坂本 就馬', 'period' => 59, 'arm' => '右', 'scores' => [257, 182, 256, 207, 266, 203, 183, 215], 'total' => 1769, 'average' => 221.12],
            ['rank' => 9, 'license' => '1439', 'name' => '増田 高英', 'period' => 60, 'arm' => '右両手', 'scores' => [194, 197, 237, 204, 214, 236, 234, 239], 'total' => 1755, 'average' => 219.37],
            ['rank' => 10, 'license' => '1492', 'name' => '横地 優輝', 'period' => 63, 'arm' => '左両手', 'scores' => [226, 209, 208, 226, 233, 228, 218, 202], 'total' => 1750, 'average' => 218.75],
            ['rank' => 11, 'license' => '1269', 'name' => '大城クラウディオ', 'period' => 51, 'arm' => '右', 'scores' => [221, 212, 215, 247, 206, 249, 189, 200], 'total' => 1739, 'average' => 217.37],
            ['rank' => 12, 'license' => '848', 'name' => '吉田 文啓', 'period' => 35, 'arm' => '右', 'scores' => [263, 212, 235, 242, 181, 236, 179, 178], 'total' => 1726, 'average' => 215.75],
            ['rank' => 13, 'license' => '1467', 'name' => '菊田 樹', 'period' => 62, 'arm' => '右両手', 'scores' => [266, 155, 203, 190, 218, 239, 246, 192], 'total' => 1709, 'average' => 213.62],
            ['rank' => 14, 'license' => '1401', 'name' => '畑野 健人', 'period' => 57, 'arm' => '右両手', 'scores' => [234, 182, 173, 202, 194, 240, 278, 205], 'total' => 1708, 'average' => 213.50],
            ['rank' => 15, 'license' => '927', 'name' => '品田順一郎', 'period' => 37, 'arm' => '右', 'scores' => [195, 243, 223, 178, 225, 214, 255, 169], 'total' => 1702, 'average' => 212.75],
            ['rank' => 16, 'license' => '1393', 'name' => '後藤 卓也', 'period' => 56, 'arm' => '右', 'scores' => [186, 244, 191, 233, 217, 225, 196, 192], 'total' => 1684, 'average' => 210.50],
            ['rank' => 17, 'license' => '889', 'name' => '斉藤 利久', 'period' => 36, 'arm' => '右', 'scores' => [216, 222, 175, 208, 245, 190, 219, 204], 'total' => 1679, 'average' => 209.87],
            ['rank' => 18, 'license' => '1471', 'name' => '千葉 幸太', 'period' => 62, 'arm' => '右', 'scores' => [213, 189, 192, 216, 211, 253, 166, 237], 'total' => 1677, 'average' => 209.62],
            ['rank' => 19, 'license' => '1419', 'name' => '大沼はるか', 'period' => 58, 'arm' => '右両手', 'scores' => [223, 208, 184, 227, 211, 196, 201, 214], 'total' => 1664, 'average' => 208.00],
            ['rank' => 20, 'license' => '1466', 'name' => '犬飼 健志', 'period' => 62, 'arm' => '右両手', 'scores' => [200, 218, 216, 248, 184, 194, 166, 235], 'total' => 1661, 'average' => 207.62],
            ['rank' => 21, 'license' => '1240', 'name' => '野々山国幸', 'period' => 49, 'arm' => '右', 'scores' => [216, 231, 248, 242, 225, 158, 180, 160], 'total' => 1660, 'average' => 207.50],
            ['rank' => 22, 'license' => '443', 'name' => '平田 三男', 'period' => 16, 'arm' => '右', 'scores' => [247, 171, 192, 267, 192, 211, 190, 177], 'total' => 1647, 'average' => 205.87],
            ['rank' => 23, 'license' => '102', 'name' => '藤村 重定', 'period' => 4, 'arm' => '右', 'scores' => [184, 192, 200, 225, 191, 195, 236, 217], 'total' => 1640, 'average' => 205.00],
            ['rank' => 24, 'license' => '1031', 'name' => '井上 純平', 'period' => 42, 'arm' => '右', 'scores' => [185, 223, 241, 214, 153, 214, 197, 199], 'total' => 1626, 'average' => 203.25],
            ['rank' => 25, 'license' => '1064', 'name' => '菅原 秀二', 'period' => 43, 'arm' => '右', 'scores' => [258, 195, 194, 167, 201, 214, 200, 188], 'total' => 1617, 'average' => 202.12],
            ['rank' => 26, 'license' => '1013', 'name' => '小金 正治', 'period' => 41, 'arm' => '右', 'scores' => [185, 214, 255, 181, 204, 209, 184, 184], 'total' => 1616, 'average' => 202.00],
            ['rank' => 27, 'license' => '964', 'name' => '大宮亜津志', 'period' => 39, 'arm' => '右', 'scores' => [204, 160, 224, 235, 230, 198, 195, 169], 'total' => 1615, 'average' => 201.87],
            ['rank' => 28, 'license' => '843', 'name' => '辻 賢司', 'period' => 35, 'arm' => '右', 'scores' => [224, 179, 202, 200, 199, 200, 219, 184], 'total' => 1607, 'average' => 200.87],
            ['rank' => 29, 'license' => '399', 'name' => '大澤 義樹', 'period' => 12, 'arm' => '右', 'scores' => [224, 208, 189, 167, 218, 186, 213, 196], 'total' => 1601, 'average' => 200.12],
            ['rank' => 30, 'license' => '1171', 'name' => '菅原 晃一', 'period' => 47, 'arm' => '右', 'scores' => [187, 185, 173, 196, 202, 232, 212, 187], 'total' => 1574, 'average' => 196.75],
            ['rank' => 31, 'license' => '1385', 'name' => '山本 一貴', 'period' => 56, 'arm' => '右', 'scores' => [183, 163, 246, 170, 244, 178, 209, 177], 'total' => 1570, 'average' => 196.25],
            ['rank' => 32, 'license' => '1412', 'name' => '田原 寬貴', 'period' => 57, 'arm' => '右両手', 'scores' => [221, 222, 141, 215, 210, 159, 213, 181], 'total' => 1562, 'average' => 195.25],
            ['rank' => 33, 'license' => '1169', 'name' => '田倉 幸則', 'period' => 47, 'arm' => '右', 'scores' => [224, 161, 136, 278, 220, 163, 193, 165], 'total' => 1540, 'average' => 192.50],
            ['rank' => 34, 'license' => '167', 'name' => '星野 宏幸', 'period' => 7, 'arm' => '右', 'scores' => [192, 216, 190, 241, 173, 171, 156, 187], 'total' => 1526, 'average' => 190.75],
        ];
    }

    private function semiRows(): array
    {
        return [
            ['rank' => 1, 'license' => '1443', 'name' => '藤永 北斗', 'period' => 61, 'arm' => '左', 'prelim_total' => 2089, 'scores' => [246, 189, 222, 242], 'semi_total' => 899, 'total_12g' => 2988, 'average_12g' => 249.00],
            ['rank' => 2, 'license' => '1384', 'name' => '水野 耕佑', 'period' => 56, 'arm' => '右', 'prelim_total' => 1852, 'scores' => [220, 214, 248, 246], 'semi_total' => 928, 'total_12g' => 2780, 'average_12g' => 231.66],
            ['rank' => 3, 'license' => '1448', 'name' => '木村 謙太', 'period' => 61, 'arm' => '右', 'prelim_total' => 1847, 'scores' => [238, 179, 224, 242], 'semi_total' => 883, 'total_12g' => 2730, 'average_12g' => 227.50],
            ['rank' => 4, 'license' => '1307', 'name' => '播摩 友和', 'period' => 52, 'arm' => '右', 'prelim_total' => 1830, 'scores' => [235, 174, 268, 208], 'semi_total' => 885, 'total_12g' => 2715, 'average_12g' => 226.25],
            ['rank' => 5, 'license' => '1492', 'name' => '横地 優輝', 'period' => 63, 'arm' => '左両手', 'prelim_total' => 1750, 'scores' => [267, 212, 233, 251], 'semi_total' => 963, 'total_12g' => 2713, 'average_12g' => 226.08],
            ['rank' => 6, 'license' => '1471', 'name' => '千葉 幸太', 'period' => 62, 'arm' => '右', 'prelim_total' => 1677, 'scores' => [233, 246, 241, 275], 'semi_total' => 995, 'total_12g' => 2672, 'average_12g' => 222.66],
            ['rank' => 7, 'license' => '1394', 'name' => '藤村 隆史', 'period' => 57, 'arm' => '右', 'prelim_total' => 1816, 'scores' => [238, 214, 209, 193], 'semi_total' => 854, 'total_12g' => 2670, 'average_12g' => 222.50],
            ['rank' => 8, 'license' => '1023', 'name' => '岡野 秀幸', 'period' => 42, 'arm' => '右', 'prelim_total' => 1830, 'scores' => [194, 210, 191, 236], 'semi_total' => 831, 'total_12g' => 2661, 'average_12g' => 221.75],
            ['rank' => 9, 'license' => '1439', 'name' => '増田 高英', 'period' => 60, 'arm' => '右両手', 'prelim_total' => 1755, 'scores' => [183, 245, 270, 207], 'semi_total' => 905, 'total_12g' => 2660, 'average_12g' => 221.66],
            ['rank' => 10, 'license' => '1401', 'name' => '畑野 健人', 'period' => 57, 'arm' => '右両手', 'prelim_total' => 1708, 'scores' => [236, 265, 232, 215], 'semi_total' => 948, 'total_12g' => 2656, 'average_12g' => 221.33],
            ['rank' => 11, 'license' => '1452', 'name' => '内藤 広人', 'period' => 61, 'arm' => '右', 'prelim_total' => 1796, 'scores' => [234, 184, 197, 227], 'semi_total' => 842, 'total_12g' => 2638, 'average_12g' => 219.83],
            ['rank' => 12, 'license' => '1467', 'name' => '菊田 樹', 'period' => 62, 'arm' => '右両手', 'prelim_total' => 1709, 'scores' => [196, 289, 205, 201], 'semi_total' => 891, 'total_12g' => 2600, 'average_12g' => 216.66],
            ['rank' => 13, 'license' => '1424', 'name' => '坂本 就馬', 'period' => 59, 'arm' => '右', 'prelim_total' => 1769, 'scores' => [227, 225, 180, 191], 'semi_total' => 823, 'total_12g' => 2592, 'average_12g' => 216.00],
            ['rank' => 14, 'license' => '1269', 'name' => '大城クラウディオ', 'period' => 51, 'arm' => '右', 'prelim_total' => 1739, 'scores' => [205, 193, 210, 196], 'semi_total' => 804, 'total_12g' => 2543, 'average_12g' => 211.91],
            ['rank' => 15, 'license' => '848', 'name' => '吉田 文啓', 'period' => 35, 'arm' => '右', 'prelim_total' => 1726, 'scores' => [187, 175, 173, 206], 'semi_total' => 741, 'total_12g' => 2467, 'average_12g' => 205.58],
            ['rank' => 16, 'license' => '889', 'name' => '斉藤 利久', 'period' => 36, 'arm' => '右', 'prelim_total' => 1679, 'scores' => [196, 157, 196, 205], 'semi_total' => 754, 'total_12g' => 2433, 'average_12g' => 202.75],
            ['rank' => 17, 'license' => '1393', 'name' => '後藤 卓也', 'period' => 56, 'arm' => '右', 'prelim_total' => 1684, 'scores' => [221, 173, 175, 157], 'semi_total' => 726, 'total_12g' => 2410, 'average_12g' => 200.83],
            ['rank' => 18, 'license' => '927', 'name' => '品田順一郎', 'period' => 37, 'arm' => '右', 'prelim_total' => 1702, 'scores' => [169, 192, 162, 175], 'semi_total' => 698, 'total_12g' => 2400, 'average_12g' => 200.00],
        ];
    }

    private function finalRows(): array
    {
        return [
            ['ranking' => 1, 'license' => '1443', 'name' => '藤永 北斗', 'period' => 61, 'points' => 68, 'award_points' => 50, 'step_points' => 18, 'prize_money' => 65000, 'total_pin' => 2988, 'average' => 249.00, 'affiliation' => 'N&K Co.,Ltd./サンブリッジ'],
            ['ranking' => 2, 'license' => '1448', 'name' => '木村 謙太', 'period' => 61, 'points' => 57, 'award_points' => 40, 'step_points' => 17, 'prize_money' => 46800, 'total_pin' => 2730, 'average' => 227.50, 'affiliation' => 'N&K Co.,Ltd./狐ヶ崎ヤングランドボウル/HI-SP'],
            ['ranking' => 3, 'license' => '1384', 'name' => '水野 耕佑', 'period' => 56, 'points' => 51, 'award_points' => 35, 'step_points' => 16, 'prize_money' => 31200, 'total_pin' => 2780, 'average' => 231.66, 'affiliation' => '岩屋キャノンボウル'],
            ['ranking' => 4, 'license' => '1307', 'name' => '播摩 友和', 'period' => 52, 'points' => 45, 'award_points' => 30, 'step_points' => 15, 'prize_money' => 28600, 'total_pin' => 2715, 'average' => 226.25, 'affiliation' => null],
            ['ranking' => 5, 'license' => '1394', 'name' => '藤村 隆史', 'period' => 57, 'points' => 39, 'award_points' => 25, 'step_points' => 14, 'prize_money' => 26000, 'total_pin' => 2670, 'average' => 222.50, 'affiliation' => null],
            ['ranking' => 6, 'license' => '1492', 'name' => '横地 優輝', 'period' => 63, 'points' => 36, 'award_points' => 23, 'step_points' => 13, 'prize_money' => 23400, 'total_pin' => 2713, 'average' => 226.08, 'affiliation' => null],
            ['ranking' => 7, 'license' => '1471', 'name' => '千葉 幸太', 'period' => 62, 'points' => 32, 'award_points' => 20, 'step_points' => 12, 'prize_money' => 20800, 'total_pin' => 2672, 'average' => 222.66, 'affiliation' => null],
            ['ranking' => 8, 'license' => '1023', 'name' => '岡野 秀幸', 'period' => 42, 'points' => 29, 'award_points' => 18, 'step_points' => 11, 'prize_money' => 18200, 'total_pin' => 2661, 'average' => 221.75, 'affiliation' => null],
        ];
    }
}
