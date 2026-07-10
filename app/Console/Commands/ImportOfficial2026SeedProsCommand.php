<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Models\ProBowlerRankingRow;
use App\Models\ProBowlerRankingSnapshot;
use App\Models\ProBowlerSeedListPlayer;
use App\Services\ProBowlerSeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOfficial2026SeedProsCommand extends Command
{
    protected $signature = 'jpba:import-official-2026-seed-pros
        {--force : Actually write the official 2026 seed data. Without this option, this command is dry-run only}
        {--json : Output JSON report}';

    protected $description = 'One-time import of 2026 tournament seed pros from official JPBA seed page and 2025 final point ranking PDFs.';

    private const SEED_YEAR = 2026;
    private const BASE_RANKING_YEAR = 2025;
    private const OFFICIAL_SEED_PAGE_URL = 'https://www.jpba.or.jp/information/tournament/seed.html';
    private const MEN_RANKING_PDF_URL = 'https://www.jpba.or.jp/information/tournament/ranking/2025/M/M_PointRanking_251220.pdf';
    private const WOMEN_RANKING_PDF_URL = 'https://www.jpba.or.jp/information/tournament/ranking/2025/W/W_PointRanking_251213.pdf';

    public function handle(ProBowlerSeedService $seedService): int
    {
        $force = (bool) $this->option('force');
        $datasets = $this->datasets();

        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'seed_year' => self::SEED_YEAR,
            'base_ranking_year' => self::BASE_RANKING_YEAR,
            'sources' => [
                'official_seed_page' => self::OFFICIAL_SEED_PAGE_URL,
                'men_final_ranking_pdf' => self::MEN_RANKING_PDF_URL,
                'women_final_ranking_pdf' => self::WOMEN_RANKING_PDF_URL,
            ],
            'summary' => [],
            'missing_bowlers' => [],
            'name_warnings' => [],
            'snapshots' => [],
            'seed_lists' => [],
        ];

        $prepared = [];

        foreach ($datasets as $gender => $dataset) {
            $rows = [];

            foreach ($dataset['rows'] as $row) {
                $licenseNo = $this->normalizeLicenseNo($gender, (string) $row['license']);
                $bowler = $this->findBowler($gender, $licenseNo);

                if (! $bowler) {
                    $report['missing_bowlers'][] = [
                        'gender' => $gender,
                        'rank' => $row['rank'],
                        'license_no' => $licenseNo,
                        'name_kanji' => $row['name'],
                    ];
                } elseif ($this->normalizeName((string) $bowler->name_kanji) !== $this->normalizeName((string) $row['name'])) {
                    $report['name_warnings'][] = [
                        'gender' => $gender,
                        'rank' => $row['rank'],
                        'license_no' => $licenseNo,
                        'source_name' => $row['name'],
                        'db_name' => $bowler->name_kanji,
                    ];
                }

                [$organizationName, $equipmentContract] = $this->splitAffiliation((string) ($row['affiliation'] ?? ''));

                $rows[] = [
                    'ranking_rank' => (int) $row['rank'],
                    'pro_bowler_id' => $bowler?->id,
                    'license_no' => $licenseNo,
                    'name_kanji' => $row['name'],
                    'name_kana' => $bowler?->name_kana,
                    'kibetsu' => $row['kibetsu'] ?? $bowler?->kibetsu,
                    'organization_name' => $organizationName,
                    'equipment_contract' => $equipmentContract,
                    'points' => $row['points'],
                    'games' => $row['games'],
                    'total_pin' => $row['total_pin'],
                    'average' => $row['average'],
                    'prize_money' => $row['prize_money'],
                    'sort_order' => (int) $row['rank'],
                ];
            }

            $prepared[$gender] = [
                'dataset' => $dataset,
                'rows' => $rows,
            ];

            $report['summary'][$gender] = [
                'source_row_count' => count($dataset['rows']),
                'prepared_row_count' => count($rows),
                'seed_top_count' => $dataset['seed_top_count'],
            ];
        }

        if ($report['missing_bowlers'] !== []) {
            $this->outputReport($report);

            return self::FAILURE;
        }

        if (! $force) {
            $this->outputReport($report);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($prepared, $seedService, &$report) {
            foreach ($prepared as $gender => $preparedDataset) {
                $dataset = $preparedDataset['dataset'];
                $rows = $preparedDataset['rows'];

                $snapshot = ProBowlerRankingSnapshot::query()->firstOrNew([
                    'ranking_year' => self::BASE_RANKING_YEAR,
                    'gender' => $gender,
                    'ranking_type' => 'points',
                    'ranking_scope' => 'official_tournament',
                    'is_final' => true,
                ]);

                $snapshot->fill([
                    'as_of_date' => $dataset['as_of_date'],
                    'source_url' => $dataset['ranking_source_url'],
                    'notes' => $dataset['snapshot_notes'],
                ]);
                $snapshot->save();

                ProBowlerRankingRow::query()
                    ->where('ranking_snapshot_id', $snapshot->id)
                    ->delete();

                foreach ($rows as $row) {
                    ProBowlerRankingRow::query()->create($row + [
                        'ranking_snapshot_id' => $snapshot->id,
                    ]);
                }

                $seedList = $seedService->createSeedListFromRanking(
                    rankingSnapshot: $snapshot,
                    seedYear: self::SEED_YEAR,
                    gender: $gender,
                    topCount: (int) $dataset['seed_top_count'],
                    options: [
                        'as_of_date' => $dataset['as_of_date'],
                        'source_url' => self::OFFICIAL_SEED_PAGE_URL,
                        'is_active' => true,
                        'notes' => $dataset['seed_list_notes'],
                    ]
                );

                ProBowlerSeedListPlayer::query()
                    ->where('seed_list_id', $seedList->id)
                    ->orderBy('seed_rank')
                    ->get()
                    ->each(function (ProBowlerSeedListPlayer $player) use ($gender) {
                        $rank = (int) $player->seed_rank;
                        $player->note = $gender === 'F'
                            ? (($rank <= 18 ? '第1シード' : '第2シード') . " / 2025年度最終ランキング{$rank}位")
                            : "2025年度最終ランキング{$rank}位";
                        $player->save();
                    });

                $report['snapshots'][$gender] = [
                    'id' => $snapshot->id,
                    'row_count' => $snapshot->rows()->count(),
                    'as_of_date' => optional($snapshot->as_of_date)->format('Y-m-d'),
                    'source_url' => $snapshot->source_url,
                ];

                $report['seed_lists'][$gender] = [
                    'id' => $seedList->id,
                    'row_count' => $seedList->players()->count(),
                    'base_top_count' => $seedList->base_top_count,
                    'as_of_date' => optional($seedList->as_of_date)->format('Y-m-d'),
                    'source_url' => $seedList->source_url,
                ];
            }
        });

        $this->outputReport($report);

        return self::SUCCESS;
    }

    private function outputReport(array $report): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return;
        }

        $this->info('2026 official seed pros import: ' . $report['mode']);

        foreach ($report['summary'] as $gender => $summary) {
            $this->line(sprintf(
                '%s: source=%d prepared=%d seed_top_count=%d',
                $gender,
                $summary['source_row_count'],
                $summary['prepared_row_count'],
                $summary['seed_top_count']
            ));
        }

        if ($report['missing_bowlers'] !== []) {
            $this->error('Missing pro bowlers found. No data was written.');

            foreach ($report['missing_bowlers'] as $missing) {
                $this->line(sprintf(
                    '- %s rank %d %s %s',
                    $missing['gender'],
                    $missing['rank'],
                    $missing['license_no'],
                    $missing['name_kanji']
                ));
            }
        }

        if ($report['name_warnings'] !== []) {
            $this->warn('Name differences were found after license matching.');

            foreach (array_slice($report['name_warnings'], 0, 10) as $warning) {
                $this->line(sprintf(
                    '- %s rank %d %s source=%s db=%s',
                    $warning['gender'],
                    $warning['rank'],
                    $warning['license_no'],
                    $warning['source_name'],
                    $warning['db_name']
                ));
            }

            if (count($report['name_warnings']) > 10) {
                $this->line('...and ' . (count($report['name_warnings']) - 10) . ' more');
            }
        }
    }

    private function findBowler(string $gender, string $licenseNo): ?ProBowler
    {
        $licenseNumber = (int) mb_substr($licenseNo, 1);

        return ProBowler::query()
            ->where(function ($query) use ($gender, $licenseNo, $licenseNumber) {
                $query->where('license_no', $licenseNo)
                    ->orWhere(function ($query) use ($gender, $licenseNumber) {
                        $query->where('license_no_num', $licenseNumber)
                            ->where('license_no', 'like', $gender . '%');
                    });
            })
            ->orderBy('id')
            ->first();
    }

    private function normalizeLicenseNo(string $gender, string $license): string
    {
        $license = strtoupper(trim($license));

        if (preg_match('/^[MF]\d+$/', $license) === 1) {
            $gender = mb_substr($license, 0, 1);
            $license = mb_substr($license, 1);
        }

        return $gender . str_pad((string) ((int) $license), 8, '0', STR_PAD_LEFT);
    }

    private function normalizeName(string $name): string
    {
        return str_replace([' ', '　'], '', trim($name));
    }

    private function splitAffiliation(string $affiliation): array
    {
        $affiliation = trim($affiliation);

        if ($affiliation === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*\/\s*/u', $affiliation, 2);

        return [
            $parts[0] !== '' ? $parts[0] : null,
            isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null,
        ];
    }

    private function datasets(): array
    {
        return [
            'M' => [
                'as_of_date' => '2025-12-23',
                'ranking_source_url' => self::MEN_RANKING_PDF_URL,
                'seed_top_count' => 24,
                'snapshot_notes' => '公式PDF「2025男子プロボウリング 最終ポイントランキング」から2026年度男子シード用に写し取り。',
                'seed_list_notes' => '2026年度男子トーナメントシードプロ。公式シードページおよび2025年度男子最終ポイントランキング上位24名を写し取り。',
                'rows' => [
                    ['rank' => 1, 'license' => '1423', 'name' => '安里 秀策', 'kibetsu' => 59, 'affiliation' => '(株)コロナワールド', 'games' => 248, 'total_pin' => 54669, 'average' => 220.43, 'points' => 3366, 'prize_money' => 3005800],
                    ['rank' => 2, 'license' => '1452', 'name' => '内藤 広人', 'kibetsu' => 61, 'affiliation' => '岩屋ｷｬﾉﾝﾎﾞｳﾙ･(株)ｿｼｵｼﾞｬﾊﾟﾝ', 'games' => 278, 'total_pin' => 61358, 'average' => 220.71, 'points' => 3036, 'prize_money' => 4019400],
                    ['rank' => 3, 'license' => '1443', 'name' => '藤永 北斗', 'kibetsu' => 61, 'affiliation' => 'N&KCo.,Ltd./STEEL SPORTS', 'games' => 260, 'total_pin' => 57664, 'average' => 221.78, 'points' => 2877, 'prize_money' => 3255100],
                    ['rank' => 4, 'license' => '1446', 'name' => '斎藤 祐太', 'kibetsu' => 61, 'affiliation' => '(株)ボウルスター', 'games' => 276, 'total_pin' => 60649, 'average' => 219.74, 'points' => 2830, 'prize_money' => 3051600],
                    ['rank' => 5, 'license' => '1445', 'name' => '宮澤 拓哉', 'kibetsu' => 61, 'affiliation' => 'ﾊﾟｰｸﾚｰﾝ高崎･上武大学/ｻﾝﾌﾞﾘｯｼﾞ', 'games' => 272, 'total_pin' => 59895, 'average' => 220.20, 'points' => 2809, 'prize_money' => 6528100],
                    ['rank' => 6, 'license' => '1345', 'name' => '甘糟 翔太', 'kibetsu' => 54, 'affiliation' => '江の島ボウリングセンター', 'games' => 290, 'total_pin' => 63748, 'average' => 219.82, 'points' => 2666, 'prize_money' => 1817300],
                    ['rank' => 7, 'license' => '1219', 'name' => '川添 奨太', 'kibetsu' => 49, 'affiliation' => 'ハイ・スポーツ社', 'games' => 251, 'total_pin' => 55690, 'average' => 221.87, 'points' => 2552, 'prize_money' => 3580700],
                    ['rank' => 8, 'license' => '1287', 'name' => '藤井 信人', 'kibetsu' => 52, 'affiliation' => 'ＩＴカンファー(株)/ﾊｲ･ｽﾎﾟｰﾂ社', 'games' => 255, 'total_pin' => 56143, 'average' => 220.16, 'points' => 2333, 'prize_money' => 1735600],
                    ['rank' => 9, 'license' => '1190', 'name' => '和田 秀和', 'kibetsu' => 48, 'affiliation' => 'ボウルアロー八尾/ABS', 'games' => 263, 'total_pin' => 56821, 'average' => 216.04, 'points' => 2206, 'prize_money' => 2607400],
                    ['rank' => 10, 'license' => '1078', 'name' => '山本 勲', 'kibetsu' => 44, 'affiliation' => 'ＡＢＳ', 'games' => 247, 'total_pin' => 55067, 'average' => 222.94, 'points' => 2143, 'prize_money' => 1971900],
                    ['rank' => 11, 'license' => '1193', 'name' => '斉藤 琢哉', 'kibetsu' => 48, 'affiliation' => '伊勢原ﾎﾞｳﾘﾝｸﾞｾﾝﾀｰ', 'games' => 235, 'total_pin' => 50851, 'average' => 216.38, 'points' => 1921, 'prize_money' => 2726200],
                    ['rank' => 12, 'license' => '1424', 'name' => '坂本 就馬', 'kibetsu' => 59, 'affiliation' => '永山コパボウル', 'games' => 229, 'total_pin' => 48277, 'average' => 210.81, 'points' => 1750, 'prize_money' => 1816000],
                    ['rank' => 13, 'license' => '974', 'name' => '永野すばる', 'kibetsu' => 40, 'affiliation' => '相模原パークレーンズ', 'games' => 257, 'total_pin' => 55535, 'average' => 216.08, 'points' => 1709, 'prize_money' => 1160300],
                    ['rank' => 14, 'license' => '1433', 'name' => '井口 遼太', 'kibetsu' => 60, 'affiliation' => 'MOTIV Bowling Products', 'games' => 276, 'total_pin' => 58393, 'average' => 211.56, 'points' => 1451, 'prize_money' => 953200],
                    ['rank' => 15, 'license' => '1478', 'name' => '倉持 悠人', 'kibetsu' => 63, 'affiliation' => '大学ボウル', 'games' => 172, 'total_pin' => 36614, 'average' => 212.87, 'points' => 1383, 'prize_money' => 1939400],
                    ['rank' => 16, 'license' => '1429', 'name' => '大久保雄矢', 'kibetsu' => 60, 'affiliation' => 'プロショップ・エム エムボウル', 'games' => 252, 'total_pin' => 54799, 'average' => 217.45, 'points' => 1351, 'prize_money' => 1371400],
                    ['rank' => 17, 'license' => '1434', 'name' => '原口 優馬', 'kibetsu' => 60, 'affiliation' => '愛知川ボウル/(株)ﾊｲ･ｽﾎﾟｰﾂ社', 'games' => 232, 'total_pin' => 51080, 'average' => 220.17, 'points' => 1230, 'prize_money' => 990000],
                    ['rank' => 18, 'license' => '1220', 'name' => '渡邉 航明', 'kibetsu' => 49, 'affiliation' => '新小岩ｻﾆｰﾎﾞｳﾙ/ﾊｲ･ｽﾎﾟｰﾂ社/さくら', 'games' => 203, 'total_pin' => 42661, 'average' => 210.15, 'points' => 1228, 'prize_money' => 664200],
                    ['rank' => 19, 'license' => '1289', 'name' => '谷合 貴志', 'kibetsu' => 52, 'affiliation' => '(株)日本ｹｱｸｵﾘﾃｨ・(有)ﾕｳｷｼｽﾃﾑｻｰﾋﾞｽ', 'games' => 209, 'total_pin' => 45621, 'average' => 218.28, 'points' => 1209, 'prize_money' => 867000],
                    ['rank' => 20, 'license' => '1233', 'name' => '斉藤 祐哉', 'kibetsu' => 49, 'affiliation' => '桜ヶ丘ﾎﾞｳﾘﾝｸﾞｾﾝﾀｰ', 'games' => 182, 'total_pin' => 39219, 'average' => 215.48, 'points' => 1132, 'prize_money' => 1036200],
                    ['rank' => 21, 'license' => '1481', 'name' => '福原 尊', 'kibetsu' => 63, 'affiliation' => '(株)コロナワールド', 'games' => 161, 'total_pin' => 34346, 'average' => 213.32, 'points' => 1081, 'prize_money' => 542200],
                    ['rank' => 22, 'license' => '1479', 'name' => '立花 仁貴', 'kibetsu' => 63, 'affiliation' => '洛陽総合高等学校', 'games' => 199, 'total_pin' => 43510, 'average' => 218.64, 'points' => 1076, 'prize_money' => 904200],
                    ['rank' => 23, 'license' => '1400', 'name' => '江川 司', 'kibetsu' => 57, 'affiliation' => '(株)GENDA GiGO Entertainment', 'games' => 201, 'total_pin' => 43566, 'average' => 216.74, 'points' => 1032, 'prize_money' => 875000],
                    ['rank' => 24, 'license' => '1395', 'name' => '佐藤 貴啓', 'kibetsu' => 57, 'affiliation' => 'ドリームスタジアム太田', 'games' => 176, 'total_pin' => 37563, 'average' => 213.42, 'points' => 962, 'prize_money' => 435000],
                ],
            ],
            'F' => [
                'as_of_date' => '2025-12-13',
                'ranking_source_url' => self::WOMEN_RANKING_PDF_URL,
                'seed_top_count' => 36,
                'snapshot_notes' => '公式PDF「2025女子プロボウリング 最終ポイントランキング」から2026年度女子第1・第2シード用に上位36名を写し取り。',
                'seed_list_notes' => '2026年度女子トーナメントシードプロ。2025年度女子最終ポイントランキング1-18位を第1シード、19-36位を第2シードとして写し取り。',
                'rows' => [
                    ['rank' => 1, 'license' => '599', 'name' => '石田 万音', 'kibetsu' => 55, 'affiliation' => 'アルゴセブン/ﾊｲ･ｽﾎﾟｰﾂ社', 'games' => 260, 'total_pin' => 55980, 'average' => 215.30, 'points' => 3868, 'prize_money' => 6754000],
                    ['rank' => 2, 'license' => '582', 'name' => '中島 瑞葵', 'kibetsu' => 53, 'affiliation' => 'フリー/ABS', 'games' => 232, 'total_pin' => 50510, 'average' => 217.71, 'points' => 3811, 'prize_money' => 3300000],
                    ['rank' => 3, 'license' => '526', 'name' => '久保田彩花', 'kibetsu' => 48, 'affiliation' => '相模原パークレーンズ', 'games' => 218, 'total_pin' => 46310, 'average' => 212.43, 'points' => 2916, 'prize_money' => 4730000],
                    ['rank' => 4, 'license' => '514', 'name' => '小久保実希', 'kibetsu' => 47, 'affiliation' => 'ジョイナスボウル/(株)ハイ･スポーツ社', 'games' => 241, 'total_pin' => 51128, 'average' => 212.14, 'points' => 2792, 'prize_money' => 2145000],
                    ['rank' => 5, 'license' => '507', 'name' => '寺下 智香', 'kibetsu' => 47, 'affiliation' => '神戸六甲ボウル/ｻﾝﾌﾞﾘｯｼﾞ', 'games' => 225, 'total_pin' => 47499, 'average' => 211.10, 'points' => 2678, 'prize_money' => 3859000],
                    ['rank' => 6, 'license' => '598', 'name' => '近藤 菜帆', 'kibetsu' => 55, 'affiliation' => 'ALSOK愛知(株)', 'games' => 221, 'total_pin' => 46330, 'average' => 209.63, 'points' => 2548, 'prize_money' => 3053000],
                    ['rank' => 7, 'license' => '559', 'name' => '霜出 佳奈', 'kibetsu' => 50, 'affiliation' => 'サンスクエアボウル/ﾊｲ･ｽﾎﾟｰﾂ社', 'games' => 227, 'total_pin' => 48152, 'average' => 212.12, 'points' => 2484, 'prize_money' => 2152000],
                    ['rank' => 8, 'license' => '352', 'name' => '姫路 麗', 'kibetsu' => 33, 'affiliation' => 'フリー', 'games' => 219, 'total_pin' => 46027, 'average' => 210.16, 'points' => 2309, 'prize_money' => 2655000],
                    ['rank' => 9, 'license' => '544', 'name' => '坂本 かや', 'kibetsu' => 49, 'affiliation' => '永山コパボウル', 'games' => 211, 'total_pin' => 44557, 'average' => 211.17, 'points' => 2141, 'prize_money' => 3543000],
                    ['rank' => 10, 'license' => '615', 'name' => '野仲 美咲', 'kibetsu' => 56, 'affiliation' => 'ココレーン', 'games' => 243, 'total_pin' => 51149, 'average' => 210.48, 'points' => 2041, 'prize_money' => 3030000],
                    ['rank' => 11, 'license' => '586', 'name' => '幸木百合菜', 'kibetsu' => 53, 'affiliation' => '相模原パークレーンズ', 'games' => 222, 'total_pin' => 45655, 'average' => 205.65, 'points' => 1954, 'prize_money' => 1585000],
                    ['rank' => 12, 'license' => '558', 'name' => '坂倉 凜', 'kibetsu' => 50, 'affiliation' => 'カニエJAPAN･ｱｿﾋﾞｯｸｽ', 'games' => 203, 'total_pin' => 43045, 'average' => 212.04, 'points' => 1823, 'prize_money' => 1960000],
                    ['rank' => 13, 'license' => '568', 'name' => '越智 真南', 'kibetsu' => 51, 'affiliation' => '平和島ｽﾀｰﾎﾞｳﾙ/ABS', 'games' => 210, 'total_pin' => 44245, 'average' => 210.69, 'points' => 1691, 'prize_money' => 1374000],
                    ['rank' => 14, 'license' => '600', 'name' => '金子 萌夏', 'kibetsu' => 55, 'affiliation' => '相模原パークレーンズ', 'games' => 225, 'total_pin' => 47385, 'average' => 210.60, 'points' => 1631, 'prize_money' => 1620000],
                    ['rank' => 15, 'license' => '494', 'name' => '桑藤 美樹', 'kibetsu' => 45, 'affiliation' => '(株)スポルト/ABS', 'games' => 196, 'total_pin' => 40419, 'average' => 206.21, 'points' => 1487, 'prize_money' => 1126000],
                    ['rank' => 16, 'license' => '525', 'name' => '宇山 侑花', 'kibetsu' => 48, 'affiliation' => '小嶺シティボウル/ABS', 'games' => 207, 'total_pin' => 42485, 'average' => 205.24, 'points' => 1395, 'prize_money' => 1085000],
                    ['rank' => 17, 'license' => '364', 'name' => '丹羽由香梨', 'kibetsu' => 35, 'affiliation' => 'カニエJAPAN･ｱｿﾋﾞｯｸｽ', 'games' => 214, 'total_pin' => 44419, 'average' => 207.56, 'points' => 1371, 'prize_money' => 972000],
                    ['rank' => 18, 'license' => '603', 'name' => '緒方 彩音', 'kibetsu' => 55, 'affiliation' => '(株)ｺﾛﾅﾜｰﾙﾄﾞ.(株)ﾎﾞｳﾙｽﾀｰ', 'games' => 222, 'total_pin' => 46236, 'average' => 208.27, 'points' => 1359, 'prize_money' => 985000],
                    ['rank' => 19, 'license' => '515', 'name' => '坂倉にいな', 'kibetsu' => 47, 'affiliation' => 'カニエJAPAN･ｱｿﾋﾞｯｸｽ', 'games' => 196, 'total_pin' => 40501, 'average' => 206.63, 'points' => 1302, 'prize_money' => 851000],
                    ['rank' => 20, 'license' => '521', 'name' => '秋光 楓', 'kibetsu' => 47, 'affiliation' => '(株)StarLike.ｱｲﾋﾞｰﾎﾞｳﾙ越谷', 'games' => 199, 'total_pin' => 40984, 'average' => 205.94, 'points' => 1257, 'prize_money' => 970000],
                    ['rank' => 21, 'license' => '583', 'name' => '堀井 春花', 'kibetsu' => 53, 'affiliation' => 'Ｊ－Ｂｏｗｌ御坊', 'games' => 226, 'total_pin' => 46871, 'average' => 207.39, 'points' => 1224, 'prize_money' => 1144000],
                    ['rank' => 22, 'license' => '533', 'name' => '川﨑 由意', 'kibetsu' => 48, 'affiliation' => 'アイキョーボウル/ｻﾝﾌﾞﾘｯｼﾞ', 'games' => 166, 'total_pin' => 34696, 'average' => 209.01, 'points' => 1207, 'prize_money' => 1005000],
                    ['rank' => 23, 'license' => '450', 'name' => '佐藤まさみ', 'kibetsu' => 42, 'affiliation' => 'ダイトースターレーン/ABS', 'games' => 208, 'total_pin' => 42865, 'average' => 206.08, 'points' => 1179, 'prize_money' => 885000],
                    ['rank' => 24, 'license' => '490', 'name' => '大根谷 愛', 'kibetsu' => 45, 'affiliation' => 'E-BOWLﾄﾏﾄ西宮', 'games' => 168, 'total_pin' => 34946, 'average' => 208.01, 'points' => 1045, 'prize_money' => 880000],
                    ['rank' => 25, 'license' => '463', 'name' => '岸田 有加', 'kibetsu' => 43, 'affiliation' => 'ACTエースレーン/STEEL SPORTS', 'games' => 185, 'total_pin' => 37881, 'average' => 204.76, 'points' => 1024, 'prize_money' => 1574000],
                    ['rank' => 26, 'license' => '365', 'name' => '名和 秋', 'kibetsu' => 35, 'affiliation' => '(株)コロナワールド', 'games' => 200, 'total_pin' => 40581, 'average' => 202.90, 'points' => 1011, 'prize_money' => 598000],
                    ['rank' => 27, 'license' => '587', 'name' => '今井 双葉', 'kibetsu' => 54, 'affiliation' => 'ＩＴカンファー(株)/ﾊｲ･ｽﾎﾟｰﾂ社', 'games' => 178, 'total_pin' => 36076, 'average' => 202.67, 'points' => 971, 'prize_money' => 742000],
                    ['rank' => 28, 'license' => '540', 'name' => '倉田 萌', 'kibetsu' => 48, 'affiliation' => 'サッポロオリンピアボウル', 'games' => 205, 'total_pin' => 42153, 'average' => 205.62, 'points' => 953, 'prize_money' => 731000],
                    ['rank' => 29, 'license' => '594', 'name' => '三上 彩奈', 'kibetsu' => 54, 'affiliation' => '(株)StarLike.STAR LIKE BOWL', 'games' => 206, 'total_pin' => 41444, 'average' => 201.18, 'points' => 924, 'prize_money' => 742000],
                    ['rank' => 30, 'license' => '524', 'name' => '山田 幸', 'kibetsu' => 48, 'affiliation' => 'ボウルアロー/ABS', 'games' => 98, 'total_pin' => 20122, 'average' => 205.32, 'points' => 832, 'prize_money' => 660000],
                    ['rank' => 31, 'license' => '543', 'name' => '松尾 星伽', 'kibetsu' => 49, 'affiliation' => 'ラウンドワンジャパン/ABS', 'games' => 173, 'total_pin' => 35305, 'average' => 204.07, 'points' => 786, 'prize_money' => 538000],
                    ['rank' => 32, 'license' => '537', 'name' => '岩見 彩乃', 'kibetsu' => 48, 'affiliation' => 'ＩＴカンファー(株)/ｻﾝﾌﾞﾘｯｼﾞ', 'games' => 200, 'total_pin' => 40248, 'average' => 201.24, 'points' => 774, 'prize_money' => 511000],
                    ['rank' => 33, 'license' => '623', 'name' => '井﨑 寛菜', 'kibetsu' => 56, 'affiliation' => '勝田パークボウル', 'games' => 199, 'total_pin' => 40389, 'average' => 202.95, 'points' => 752, 'prize_money' => 456000],
                    ['rank' => 34, 'license' => '520', 'name' => '三浦 美里', 'kibetsu' => 47, 'affiliation' => 'ラウンドワンジャパン', 'games' => 160, 'total_pin' => 32542, 'average' => 203.38, 'points' => 740, 'prize_money' => 345000],
                    ['rank' => 35, 'license' => '614', 'name' => '川田 菜摘', 'kibetsu' => 56, 'affiliation' => 'フリー/ABS', 'games' => 207, 'total_pin' => 42126, 'average' => 203.50, 'points' => 725, 'prize_money' => 587000],
                    ['rank' => 36, 'license' => '523', 'name' => '内藤真裕実', 'kibetsu' => 48, 'affiliation' => 'フリー/ｻﾝﾌﾞﾘｯｼﾞ', 'games' => 179, 'total_pin' => 36082, 'average' => 201.57, 'points' => 724, 'prize_money' => 456000],
                ],
            ],
        ];
    }
}
