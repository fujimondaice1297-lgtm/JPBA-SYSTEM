@php
    $standardAwardRows = collect($resultRows ?? [])->take(30)->values();
    $standardNumericRank = static fn ($row): int => max(
        0,
        (int) data_get($row, 'ranking', data_get($row, 'rank', 0))
    );
    $standardPrizeDistributionMap = collect($prizeDistributionMap ?? [])
        ->mapWithKeys(fn ($amount, $rank): array => [(int) $rank => max(0, (int) $amount)])
        ->all();
    $standardPrizeBreakdowns = collect(
        data_get($tournament->template_snapshot ?? [], 'pdf_prize_breakdowns', [])
    )->mapWithKeys(function ($row): array {
        $rank = (int) ($row['ranking'] ?? 0);

        return $rank > 0 ? [$rank => $row] : [];
    })->all();
    $standardPrizeTotal = $standardPrizeDistributionMap !== []
        ? array_sum($standardPrizeDistributionMap)
        : $standardAwardRows->sum(fn ($row): int => (int) ($row->prize_money ?? 0));
    $standardBasePrize = static function ($row) use (
        $standardNumericRank,
        $standardPrizeBreakdowns,
        $standardPrizeDistributionMap
    ): int {
        $rank = $standardNumericRank($row);
        $breakdownBase = data_get($standardPrizeBreakdowns, $rank . '.base_prize_money');
        if (is_numeric($breakdownBase)) {
            return max(0, (int) $breakdownBase);
        }

        return $standardPrizeDistributionMap[$rank] ?? max(0, (int) ($row->prize_money ?? 0));
    };
    $standardChampion = $standardAwardRows->first();
    $standardChampionName = $standardChampion ? $resolveName($standardChampion) : '-';
    $standardChampionProfile = $standardChampion?->player ?? $standardChampion?->bowler ?? null;
    $standardChampionTitles = (int) (
        $standardChampionProfile->official_title_count
        ?? $standardChampionProfile->title_count
        ?? 0
    );
    $standardChampionRank = $standardChampion ? $standardNumericRank($standardChampion) : 0;
    $standardChampionSpecialPrize = max(
        0,
        (int) data_get($standardPrizeBreakdowns, $standardChampionRank . '.special_prize_money', 0)
    );
    $standardAwardHighlights = collect($tournament->award_highlights ?? []);
    $standardPerfectHighlights = $standardAwardHighlights
        ->where('type', 'perfect')
        ->values();
    $standardSplitHighlights = $standardAwardHighlights
        ->where('type', 'split710')
        ->values();

    $standardRrByLicense = [];
    foreach ((array) ($roundRobinPdf['players'] ?? []) as $standardRrPlayer) {
        $standardRrPlayer = (array) $standardRrPlayer;
        $standardRrLicense = preg_replace(
            '/[^0-9]/',
            '',
            (string) ($standardRrPlayer['license_no'] ?? $standardRrPlayer['pro_bowler_license_no'] ?? '')
        ) ?? '';
        if ($standardRrLicense !== '') {
            $standardRrByLicense[(int) $standardRrLicense] = $standardRrPlayer;
        }
    }

    $standardAwardScore = function ($row) use ($standardNumericRank, $standardRrByLicense, $formatNumber): string {
        $rank = $standardNumericRank($row);
        if ($rank > 0 && $rank <= 3) {
            return '決勝参照';
        }

        $licenseDigits = preg_replace('/[^0-9]/', '', (string) ($row->pro_bowler_license_no ?? '')) ?? '';
        $rr = $licenseDigits !== '' ? ($standardRrByLicense[(int) $licenseDigits] ?? null) : null;
        if ($rr) {
            $games = (int) ($rr['carry_games'] ?? 0) + count((array) ($rr['rr_scores'] ?? []));
            $point = (int) ($rr['overall_total_points'] ?? 0) - ($games * 200);
            return ($point >= 0 ? '+' : '') . $formatNumber($point);
        }

        $pin = $row->total_pin ?? $row->total_score ?? null;
        return $pin === null ? '-' : $formatNumber($pin);
    };
@endphp

<div class="standard-awards-page">
    <h1 class="standard-page-title {{ $resolvedOfficialTitleClass ?? '' }}">{{ $officialMainTitle }}</h1>
    <div class="standard-awards-subtitle">
        入賞者リスト
        <span>賞金総額 {{ $standardPrizeTotal > 0 ? '￥' . number_format($standardPrizeTotal) : ($tournament->prize ?: '-') }}</span>
    </div>

    <table class="standard-awards-layout">
        <tr>
            <td class="standard-awards-list-cell">
                <table class="standard-awards-table">
                    <thead>
                        <tr>
                            <th class="std-award-rank">順位</th>
                            <th class="std-award-license">ライセンス<br>No.</th>
                            <th class="std-award-name">氏名</th>
                            <th class="std-award-period">期</th>
                            <th class="std-award-belong">所属 / 用品契約</th>
                            <th class="std-award-score">スコア</th>
                            <th class="std-award-point">ポイント</th>
                            <th class="std-award-prize">賞金（￥）</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($standardAwardRows as $row)
                            @php
                                $rank = $standardNumericRank($row);
                                $belong = $resolveBelong($row);
                            @endphp
                            <tr class="{{ in_array($rank, [3, 8, 30], true) ? 'standard-award-stage-end' : '' }}">
                                <td>{{ $resolveRank($row) }}</td>
                                <td class="pdf-license-cell">{{ $resolveLicense($row) }}</td>
                                <td class="text-left">{{ $resolveName($row) }}</td>
                                <td>{{ $resolvePeriod($row) }}</td>
                                <td class="text-left standard-award-belong-cell">
                                    <span class="{{ $belongTextClass($belong) }}">{{ $belong }}</span>
                                </td>
                                <td>{{ $standardAwardScore($row) }}</td>
                                <td>{{ $formatNumber($row->points ?? 0) }}</td>
                                <td class="text-right">{{ number_format($standardBasePrize($row)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td class="standard-awards-notes-cell">
                <div class="standard-champion-box">
                    <div class="standard-champion-label">優勝</div>
                    <div class="standard-champion-name">{{ $standardChampionName }}</div>
                    <div class="standard-champion-meta">
                        {{ $standardChampion ? $resolvePeriod($standardChampion) . '期生' : '' }}
                        @if ($standardChampionTitles > 0)
                            ／ 通算{{ number_format($standardChampionTitles) }}勝
                        @endif
                    </div>
                    <div class="standard-champion-message">大会優勝者</div>
                    @if ($standardChampionSpecialPrize > 0)
                        <div class="standard-champion-message">副賞 ￥{{ number_format($standardChampionSpecialPrize) }}</div>
                    @endif
                </div>

                <div class="standard-note-section">
                    <h3>大会記録</h3>
                    <p>決勝は3名によるステップラダー方式。</p>
                    <p>ラウンドロビン上位3名が決勝へ進出。</p>
                </div>

                <div class="standard-note-section">
                    <h3>☆ パーフェクトゲーム達成者</h3>
                    @forelse ($standardPerfectHighlights as $highlight)
                        <p>
                            {{ $highlight['player'] ?? '-' }}
                            @if (!empty($highlight['game']))（{{ $highlight['game'] }}@if (!empty($highlight['lane']))・{{ $highlight['lane'] }}@endif）@endif
                            @if (!empty($highlight['note']))<br>{{ $highlight['note'] }}@endif
                        </p>
                    @empty
                        <p>大会記録として登録された達成者を掲載します。</p>
                    @endforelse
                </div>

                <div class="standard-note-section">
                    <h3>☆ 7-10スプリットメイド達成者</h3>
                    @forelse ($standardSplitHighlights as $highlight)
                        <p>
                            {{ $highlight['player'] ?? '-' }}
                            @if (!empty($highlight['game']))（{{ $highlight['game'] }}@if (!empty($highlight['lane']))・{{ $highlight['lane'] }}@endif）@endif
                            @if (!empty($highlight['note']))<br>{{ $highlight['note'] }}@endif
                        </p>
                    @empty
                        <p>大会記録として登録された達成者を掲載します。</p>
                    @endforelse
                </div>

                <div class="standard-note-section standard-note-footer">
                    <p>会場：{{ $officialVenueTitle }}</p>
                    <p>開催日：{{ $dateText !== '' ? $dateText : '-' }}</p>
                </div>
            </td>
        </tr>
    </table>
</div>
