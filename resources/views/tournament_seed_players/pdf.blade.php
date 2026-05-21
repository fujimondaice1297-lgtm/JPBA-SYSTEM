{{-- resources/views/tournament_seed_players/pdf.blade.php --}}
@php
    $year = $tournament->year ?? (isset($tournament->start_date) ? mb_substr((string) $tournament->start_date, 0, 4) : '');
    $genderLabel = match((string)($tournament->gender ?? '')) {
        'M', '1', 'male', 'Male', '男子', '男' => '男子',
        'F', '2', 'female', 'Female', '女子', '女' => '女子',
        default => '男女',
    };

    $baseRankingYear = collect($priorityPlayers)
        ->pluck('base_ranking_year')
        ->filter()
        ->unique()
        ->values()
        ->implode(' / ');

    $sourceLabel = $baseRankingYear !== '' ? $baseRankingYear . 'ランキング' : '年度別シード・大会別追加シード';

    $allPriorityPlayers = collect($priorityPlayers);

    $sourceTypesForPlayer = function (array $player): array {
        $types = $player['seed_source_types'] ?? [];

        if (is_string($types)) {
            $types = [$types];
        }

        if (!is_array($types)) {
            $types = [];
        }

        if (!empty($player['seed_source_type'])) {
            $types[] = (string) $player['seed_source_type'];
        }

        return array_values(array_unique(array_filter(array_map('strval', $types))));
    };

    $hasAnySourceType = function (array $player, array $targetTypes) use ($sourceTypesForPlayer): bool {
        return count(array_intersect($sourceTypesForPlayer($player), $targetTypes)) > 0;
    };

    $tournamentSeedSourceTypes = [
        'seed_list',
        'previous_year_ranking_top24',
        'current_year_ranking',
    ];

    $isTournamentSeed = function (array $player) use ($hasAnySourceType, $tournamentSeedSourceTypes): bool {
        $seedLabel = (string) ($player['seed_label'] ?? '');

        return $hasAnySourceType($player, $tournamentSeedSourceTypes)
            || str_contains($seedLabel, 'トーナメントシード')
            || trim($seedLabel) === 'TS';
    };

    $tournamentSeedPlayers = $allPriorityPlayers
        ->filter(fn ($player) => is_array($player) && $isTournamentSeed($player))
        ->values();

    $nonTournamentSeedPlayers = $allPriorityPlayers
        ->filter(fn ($player) => is_array($player) && !$isTournamentSeed($player))
        ->values();

    $supplementalSections = [
        [
            'no' => '②',
            'key' => 'past_champion',
            'title' => '公認T/M歴代優勝者シードプロ',
            'title_lines' => ['公認T/M歴代優勝者シードプロ'],
            'note' => '※特殊T/Mを除く',
            'source_types' => ['past_champion'],
            'keywords' => ['公認T/M歴代優勝者シード', '歴代優勝者', 'PAST_CHAMPION'],
        ],
        [
            'no' => '③',
            'key' => 'permanent',
            'title' => '永久シードプロ（V20）',
            'title_lines' => ['永久シードプロ（V20）'],
            'note' => '',
            'source_types' => ['permanent_seed'],
            'keywords' => ['永久シード', 'V20'],
        ],
        [
            'no' => '④',
            'key' => 'all_japan',
            'title' => '全日本選手権者シード（JS）',
            'title_lines' => ['全日本選手権者シード（JS）'],
            'note' => '※5年間有効',
            'source_types' => ['all_japan_champion'],
            'keywords' => ['全日本選手権者シード', 'JS'],
        ],
        [
            'no' => '⑤',
            'key' => 'official_winner',
            'title' => '公認T/M歴代優勝者シードプロ',
            'title_lines' => ['公認T/M歴代優勝者シードプロ'],
            'note' => '※特殊T/M除く',
            'source_types' => ['current_year_winner', 'previous_year_winner'],
            'keywords' => ['当該年度優勝者', '前年度優勝者', 'CS1', 'CS2'],
        ],
        [
            'no' => '⑥',
            'key' => 'semi_permanent',
            'title' => '準永久シードプロ（V10）',
            'title_lines' => ['準永久シードプロ（V10）'],
            'note' => '',
            'source_types' => ['semi_permanent_seed'],
            'keywords' => ['準永久シード', 'V10'],
        ],
        [
            'no' => '⑦',
            'key' => 'event_sponsor',
            'title' => '本大会スポンサー推薦',
            'title_lines' => ['本大会スポンサー推薦'],
            'note' => '0名',
            'source_types' => ['event_sponsor_recommendation'],
            'keywords' => ['本大会スポンサー推薦'],
        ],
        [
            'no' => '⑧',
            'key' => 'protest_exempt',
            'title' => $year . 'プロテスト実技免除合格者',
            'title_lines' => [$year . 'プロテスト実技免除合格者'],
            'note' => '',
            'source_types' => ['pro_test_practical_exempt'],
            'keywords' => ['プロテスト実技免除'],
        ],
        [
            'no' => '⑨',
            'key' => 'protest_top',
            'title' => $year . 'プロテストトップ合格者',
            'title_lines' => [$year . 'プロテストトップ合格者'],
            'note' => '',
            'source_types' => ['pro_test_top_passer'],
            'keywords' => ['プロテストトップ'],
        ],
        [
            'no' => '⑩',
            'key' => 'season_trial',
            'title' => 'シーズントライアル出場者',
            'title_lines' => ['シーズントライアル出場者'],
            'note' => '会場別出場枠は別紙管理',
            'source_types' => ['season_trial_participant'],
            'keywords' => ['シーズントライアル', '季別'],
        ],
        [
            'no' => '⑪',
            'key' => 'organizer_recommendation',
            'title' => '主催者（スポンサー）推薦',
            'title_lines' => ['主催者（スポンサー）推薦'],
            'note' => '',
            'source_types' => ['organizer_recommendation'],
            'keywords' => ['主催者推薦'],
        ],
    ];

    $sectionPlayers = [];
    $matchedSupplementalIndexes = [];

    foreach ($supplementalSections as $section) {
        $matched = $nonTournamentSeedPlayers
            ->filter(function ($player, $index) use ($section, &$matchedSupplementalIndexes, $hasAnySourceType) {
                if (in_array($index, $matchedSupplementalIndexes, true)) {
                    return false;
                }

                $sourceTypes = $section['source_types'] ?? [];

                if (!empty($sourceTypes) && $hasAnySourceType($player, $sourceTypes)) {
                    $matchedSupplementalIndexes[] = $index;
                    return true;
                }

                $seedLabel = (string) ($player['seed_label'] ?? '');
                $sourceLabelForPlayer = (string) ($player['source_label'] ?? '');
                $note = (string) ($player['note'] ?? '');
                $haystack = $seedLabel . ' ' . $sourceLabelForPlayer . ' ' . $note;

                foreach ($section['keywords'] as $keyword) {
                    if ($keyword !== '' && str_contains($haystack, $keyword)) {
                        $matchedSupplementalIndexes[] = $index;
                        return true;
                    }
                }

                return false;
            })
            ->values();

        $sectionPlayers[$section['key']] = $matched;
    }

    $otherSupplementalPlayers = $nonTournamentSeedPlayers
        ->reject(fn ($player, $index) => in_array($index, $matchedSupplementalIndexes, true))
        ->values();
@endphp
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $year }}年 {{ $tournament->name }} 大会優先出場者一覧</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 13px 16px 16px;
        }

        * {
            box-sizing: border-box;
            font-family: ipaexg, "ipaexg", sans-serif !important;
            font-weight: normal !important;
        }

        body {
            margin: 0;
            color: #111;
            font-family: ipaexg, "ipaexg", sans-serif !important;
            font-size: 9.8px;
            line-height: 1.26;
            font-weight: normal !important;
        }

        .jpba-small {
            text-align: center;
            font-size: 7.8px;
            letter-spacing: 0.08em;
            color: #555;
            margin-bottom: 3px;
        }

        h1 {
            margin: 0;
            text-align: center;
            font-size: 19px;
            letter-spacing: 0.07em;
            font-weight: normal;
        }

        .subtitle {
            margin-top: 4px;
            text-align: center;
            font-size: 11.2px;
            font-weight: normal;
        }

        .meta {
            margin: 8px 0 7px;
            width: 100%;
            border-collapse: collapse;
            font-size: 9.1px;
        }

        .meta th,
        .meta td {
            border: 1px solid #444;
            padding: 3px 6px;
            vertical-align: middle;
        }

        .meta th {
            width: 20%;
            background: #f2f2f2;
            text-align: left;
        }

        .content-layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .content-layout > tbody > tr > td {
            vertical-align: top;
        }

        .left-panel {
            width: 52%;
            padding-right: 5px;
        }

        .right-panel {
            width: 48%;
            padding-left: 5px;
        }

        .section-title {
            margin: 0 0 4px;
            padding: 3px 5px;
            background: #e9ecef;
            border: 1px solid #555;
            font-size: 9.4px;
        }

        .priority-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.2px;
        }

        .priority-table th,
        .priority-table td {
            border: 1px solid #555;
            padding: 2.2px 2px;
            vertical-align: middle;
            overflow-wrap: normal;
            word-break: keep-all;
        }

        .priority-table thead th {
            background: #f3f3f3;
            text-align: center;
            line-height: 1.16;
            font-size: 8.8px;
            white-space: nowrap;
        }

        .w-priority { width: 12%; }
        .w-rank { width: 13%; }
        .w-license { width: 11%; }
        .w-name { width: 50%; }
        .w-period { width: 5%; }
        .w-seed { width: 9%; }

        .mini-section {
            margin-bottom: 4px;
            page-break-inside: avoid;
        }

        .mini-title {
            border: 1px solid #333;
            border-bottom: none;
            padding: 3px 5px 2px;
            font-size: 8.9px;
            line-height: 1.16;
            background: #f3f3f3;
            white-space: nowrap;
        }

        .mini-title-no {
            display: inline-block;
            width: 15px;
            text-align: center;
            vertical-align: top;
            white-space: nowrap;
        }

        .mini-title-main {
            display: inline-block;
            width: 222px;
            vertical-align: top;
            white-space: nowrap;
        }

        .mini-title-line {
            display: inline;
            white-space: nowrap;
        }

        .mini-title-count {
            display: inline-block;
            width: 34px;
            text-align: right;
            vertical-align: top;
            white-space: nowrap;
        }

        .mini-note {
            font-size: 8.2px;
            color: #333;
            margin-left: 20px;
            line-height: 1.08;
            white-space: nowrap;
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.0px;
        }

        .mini-table th,
        .mini-table td {
            border: 1px solid #333;
            padding: 2.2px 2px;
            vertical-align: middle;
            overflow-wrap: normal;
            word-break: keep-all;
        }

        .mini-table th {
            background: #f8f8f8;
            text-align: center;
            line-height: 1.15;
        }

        .empty-box {
            border: 1px solid #333;
            padding: 4px 5px;
            font-size: 8.9px;
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .license {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .note {
            margin-top: 7px;
            font-size: 8.8px;
            color: #333;
        }

        .footer {
            position: fixed;
            bottom: -7px;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 7.6px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="jpba-small">JAPAN PROFESSIONAL BOWLING ASSOCIATION</div>
    <h1>{{ $year }}年 {{ $tournament->name }}</h1>
    <div class="subtitle">大会優先出場者一覧</div>

    <table class="meta">
        <tbody>
            <tr>
                <th>大会名</th>
                <td>{{ $tournament->name }}</td>
                <th>対象</th>
                <td>{{ $genderLabel }}</td>
            </tr>
            <tr>
                <th>年度</th>
                <td>{{ $year }}</td>
                <th>参照元</th>
                <td>{{ $sourceLabel }}</td>
            </tr>
            <tr>
                <th>開催日</th>
                <td colspan="3">
                    {{ $tournament->start_date ?? '-' }}
                    @if(!empty($tournament->end_date) && (string)$tournament->end_date !== (string)($tournament->start_date ?? ''))
                        〜 {{ $tournament->end_date }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <table class="content-layout">
        <tbody>
            <tr>
                <td class="left-panel">
                    <div class="section-title">① トーナメントシードプロ（TS）</div>

                    @if($tournamentSeedPlayers->isEmpty())
                        <div class="empty-box">該当者なし（0名）</div>
                    @else
                        <table class="priority-table">
                            <thead>
                                <tr>
                                    <th class="w-priority"><span>{{ $year }}年</span><br><span>優先順位</span></th>
                                    <th class="w-rank"><span>{{ (int) $baseRankingYear > 0 ? $baseRankingYear : '前年' }}</span><br><span>ランキング</span></th>
                                    <th class="w-license"><span>ライセンス</span><br><span>No</span></th>
                                    <th class="w-name">氏名</th>
                                    <th class="w-period">期</th>
                                    <th class="w-seed">種別</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tournamentSeedPlayers as $player)
                                    <tr>
                                        <td class="text-right">{{ $player['priority_no'] ?? '-' }}</td>
                                        <td class="text-right">{{ $player['ranking_rank'] ?? '-' }}</td>
                                        <td class="license">{{ $player['license_no'] ?? '-' }}</td>
                                        <td>{{ $player['name'] ?? '-' }}</td>
                                        <td class="text-center">{{ $player['period_label'] ?? '-' }}</td>
                                        <td class="text-center">TS</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if($otherSupplementalPlayers->isNotEmpty())
                        <div class="mini-section" style="margin-top: 6px;">
                            <div class="mini-title"><span class="mini-title-no">他</span> その他大会別追加シード</div>
                            <table class="mini-table">
                                <thead>
                                    <tr>
                                        <th style="width: 12%;">優先</th>
                                        <th style="width: 18%;">ライセンスNo</th>
                                        <th style="width: 48%;">氏名</th>
                                        <th style="width: 8%;">期</th>
                                        <th style="width: 14%;">種別</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($otherSupplementalPlayers as $player)
                                        <tr>
                                            <td class="text-right">{{ $player['priority_no'] ?? '-' }}</td>
                                            <td class="license">{{ $player['license_no'] ?? '-' }}</td>
                                            <td>{{ $player['name'] ?? '-' }}</td>
                                            <td class="text-center">{{ $player['period_label'] ?? '-' }}</td>
                                            <td>{{ $player['seed_label'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </td>

                <td class="right-panel">
                    @foreach($supplementalSections as $section)
                        @php
                            $players = $sectionPlayers[$section['key']] ?? collect();
                        @endphp
                        <div class="mini-section">
                            <div class="mini-title">
                                <span class="mini-title-no">{{ $section['no'] }}</span>
                                <span class="mini-title-main">
                                    @foreach(($section['title_lines'] ?? [$section['title']]) as $titleLine)
                                        <span class="mini-title-line">{{ $titleLine }}</span>
                                    @endforeach
                                </span>
                                <span class="mini-title-count">（{{ $players->count() }}名）</span>
                                @if(!empty($section['note']))
                                    <div class="mini-note">{{ $section['note'] }}</div>
                                @endif
                            </div>

                            @if($players->isEmpty())
                                <div class="empty-box">該当者なし（0名）</div>
                            @else
                                <table class="mini-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 12%;">優先</th>
                                            <th style="width: 18%;">ライセンスNo</th>
                                            <th style="width: 60%;">氏名</th>
                                            <th style="width: 10%;">期</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($players as $player)
                                            <tr>
                                                <td class="text-right">{{ $player['priority_no'] ?? '-' }}</td>
                                                <td class="license">{{ $player['license_no'] ?? '-' }}</td>
                                                <td>{{ $player['name'] ?? '-' }}</td>
                                                <td class="text-center">{{ $player['period_label'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @endforeach
                </td>
            </tr>
        </tbody>
    </table>

    <div class="note">
        ※この一覧は、年度別シード一覧と大会別追加シードをもとに自動生成しています。<br>
        ※会場別出場枠、スポンサー推薦、プロテスト枠、シーズントライアル枠などは、該当データ登録後に反映します。
    </div>

    <div class="footer">{{ $year }} {{ $tournament->name }} / Priority List</div>
</body>
</html>
