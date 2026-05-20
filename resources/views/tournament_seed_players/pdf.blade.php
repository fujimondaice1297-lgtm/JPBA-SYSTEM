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

    $isTournamentSeed = function (array $player): bool {
        $seedLabel = (string) ($player['seed_label'] ?? '');
        return str_contains($seedLabel, 'トーナメントシード') || trim($seedLabel) === 'TS';
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
            'note' => '※特殊T/Mを除く',
            'keywords' => ['歴代優勝者', 'PAST_CHAMPION'],
        ],
        [
            'no' => '③',
            'key' => 'permanent',
            'title' => '永久シードプロ（V20）',
            'note' => '',
            'keywords' => ['永久シード', 'V20'],
        ],
        [
            'no' => '④',
            'key' => 'all_japan',
            'title' => '全日本選手権者シード（JS）',
            'note' => '※5年間有効',
            'keywords' => ['全日本選手権者シード', 'JS'],
        ],
        [
            'no' => '⑤',
            'key' => 'official_winner',
            'title' => '公認T/M歴代優勝者シードプロ',
            'note' => '※特殊T/M除く',
            'keywords' => ['公認トーナメント優勝者', '当該年度優勝者', '前年度優勝者', 'CS1', 'CS2'],
        ],
        [
            'no' => '⑥',
            'key' => 'semi_permanent',
            'title' => '準永久シードプロ（V10）',
            'note' => '',
            'keywords' => ['準永久シード', 'V10'],
        ],
        [
            'no' => '⑦',
            'key' => 'event_sponsor',
            'title' => '本大会スポンサー推薦',
            'note' => '0名',
            'keywords' => ['本大会スポンサー推薦', 'スポンサー推薦'],
        ],
        [
            'no' => '⑧',
            'key' => 'protest_exempt',
            'title' => $year . 'プロテスト実技免除合格者',
            'note' => '',
            'keywords' => ['プロテスト実技免除'],
        ],
        [
            'no' => '⑨',
            'key' => 'protest_top',
            'title' => $year . 'プロテストトップ合格者',
            'note' => '',
            'keywords' => ['プロテストトップ'],
        ],
        [
            'no' => '⑩',
            'key' => 'season_trial',
            'title' => 'シーズントライアル出場者',
            'note' => '会場別出場枠は別紙管理',
            'keywords' => ['シーズントライアル', '季別'],
        ],
        [
            'no' => '⑪',
            'key' => 'organizer_recommendation',
            'title' => '主催者（スポンサー）推薦',
            'note' => '',
            'keywords' => ['主催者推薦', 'スポンサー推薦', '推薦'],
        ],
    ];

    $sectionPlayers = [];
    $matchedSupplementalIndexes = [];

    foreach ($supplementalSections as $section) {
        $matched = $nonTournamentSeedPlayers
            ->filter(function ($player, $index) use ($section, &$matchedSupplementalIndexes) {
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
            margin: 18px 20px 20px;
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
            font-size: 8px;
            line-height: 1.22;
            font-weight: normal !important;
        }

        .jpba-small {
            text-align: center;
            font-size: 7px;
            letter-spacing: 0.08em;
            color: #555;
            margin-bottom: 3px;
        }

        h1 {
            margin: 0;
            text-align: center;
            font-size: 17px;
            letter-spacing: 0.07em;
            font-weight: normal;
        }

        .subtitle {
            margin-top: 4px;
            text-align: center;
            font-size: 10px;
            font-weight: normal;
        }

        .meta {
            margin: 10px 0 7px;
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }

        .meta th,
        .meta td {
            border: 1px solid #444;
            padding: 3px 5px;
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
            width: 58%;
            padding-right: 6px;
        }

        .right-panel {
            width: 42%;
            padding-left: 4px;
        }

        .section-title {
            margin: 0 0 4px;
            padding: 3px 5px;
            background: #e9ecef;
            border: 1px solid #555;
            font-size: 8px;
        }

        .priority-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.3px;
        }

        .priority-table th,
        .priority-table td {
            border: 1px solid #555;
            padding: 2px 3px;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }

        .priority-table thead th {
            background: #f3f3f3;
            text-align: center;
        }

        .w-priority { width: 15%; }
        .w-rank { width: 12%; }
        .w-license { width: 20%; }
        .w-name { width: 35%; }
        .w-period { width: 8%; }
        .w-seed { width: 10%; }

        .mini-section {
            margin-bottom: 6px;
            page-break-inside: avoid;
        }

        .mini-title {
            border: 1px solid #333;
            border-bottom: none;
            padding: 3px 5px;
            font-size: 8px;
            background: #f3f3f3;
        }

        .mini-title-no {
            display: inline-block;
            width: 18px;
            text-align: center;
        }

        .mini-note {
            font-size: 7px;
            color: #333;
            margin-left: 20px;
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.2px;
        }

        .mini-table th,
        .mini-table td {
            border: 1px solid #333;
            padding: 2px 3px;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }

        .mini-table th {
            background: #f8f8f8;
            text-align: center;
        }

        .empty-box {
            border: 1px solid #333;
            padding: 5px;
            font-size: 7.5px;
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
            font-size: 7px;
            color: #333;
        }

        .footer {
            position: fixed;
            bottom: -8px;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 7px;
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
                                    <th class="w-priority">{{ $year }}年<br>優先順位</th>
                                    <th class="w-rank">{{ (int) $baseRankingYear > 0 ? $baseRankingYear : '前年' }}<br>ランキング</th>
                                    <th class="w-license">ライセンスNo</th>
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
                                        <th style="width: 16%;">優先</th>
                                        <th style="width: 24%;">ライセンスNo</th>
                                        <th style="width: 36%;">氏名</th>
                                        <th style="width: 8%;">期</th>
                                        <th style="width: 16%;">種別</th>
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
                                <span class="mini-title-no">{{ $section['no'] }}</span>{{ $section['title'] }}
                                <span>（{{ $players->count() }}名）</span>
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
                                            <th style="width: 16%;">優先</th>
                                            <th style="width: 26%;">ライセンスNo</th>
                                            <th style="width: 42%;">氏名</th>
                                            <th style="width: 16%;">期</th>
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
