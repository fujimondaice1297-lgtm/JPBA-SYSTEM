@php
    $formatOverviewDate = function ($value): string {
        if ($value === null || $value === '') return '-';
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y年n月j日');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $overviewStart = $formatOverviewDate($tournament->start_date ?? null);
    $overviewEnd = $formatOverviewDate($tournament->end_date ?? null);
    $overviewDate = $overviewStart === $overviewEnd || $overviewEnd === '-'
        ? $overviewStart
        : $overviewStart . ' ～ ' . $overviewEnd;
    $hasSemifinalOverview = collect($pdfScoreSnapshots ?? [])
        ->contains(fn ($set): bool => trim((string) (($set['snapshot']->result_code ?? ''))) === 'semifinal_total');
    $hasRoundRobinOverview = !empty($roundRobinPdf['players'] ?? []);
    $overviewStages = ['予選：' . (int) ($prelimGameCount ?? 0) . 'ゲーム'];
    if ($hasSemifinalOverview) {
        $overviewStages[] = '準決勝：' . (int) ($semifinalGameCount ?? 0) . 'ゲーム（予選からの通算成績）';
    }
    if ($hasRoundRobinOverview) {
        $rrMeta = (array) ($roundRobinPdf['meta'] ?? []);
        $overviewStages[] = '決勝ラウンドロビン：'
            . (int) ($rrMeta['qualifier_count'] ?? count($roundRobinPdf['players'] ?? []))
            . '名・' . (int) ($rrMeta['round_robin_games'] ?? 0) . 'ゲーム';
        $overviewStages[] = '決勝ステップラダー：ラウンドロビン上位3名';
    } else {
        $overviewStages[] = '決勝：' . (string) ($finalFormatLabel ?? '登録済み大会方式');
    }
    $overviewPrizeTotal = collect($resultRows ?? [])
        ->sum(fn ($row): int => (int) ($row->prize_money ?? 0));
@endphp

<div class="official-standard-overview-page jpba-heavy">
    <h1 class="official-standard-overview-title {{ $resolvedOfficialTitleClass ?? '' }}">{{ $officialMainTitle }}</h1>
    <h2 class="official-standard-overview-subtitle">大会概要・競技要項</h2>

    <table class="official-standard-overview-table">
        <tr><th>主　催</th><td>{{ $tournament->host ?: '公益社団法人 日本プロボウリング協会' }}</td></tr>
        <tr><th>公　認</th><td>{{ $tournament->authorized_by ?: '公益社団法人 日本プロボウリング協会' }}</td></tr>
        <tr><th>開催期日</th><td>{{ $overviewDate }}</td></tr>
        <tr>
            <th>会　場</th>
            <td>
                {{ $tournament->venue_name ?: $officialVenueTitle }}
                @if (trim((string) ($tournament->venue_address ?? '')) !== '')<br>{{ $tournament->venue_address }}@endif
                @if (trim((string) ($tournament->venue_tel ?? '')) !== '') ／ TEL {{ $tournament->venue_tel }}@endif
            </td>
        </tr>
        <tr><th>競技種目</th><td>{{ $tournament->competition_type === 'singles' ? '個人戦' : ($tournament->competition_type ?: '登録済み大会方式') }}</td></tr>
        <tr><th>出場者</th><td>{{ count($resultRows ?? []) }}名</td></tr>
        <tr>
            <th>競技方法</th>
            <td>
                @foreach ($overviewStages as $index => $stage)
                    {{ $index + 1 }}．{{ $stage }}@if (!$loop->last)<br>@endif
                @endforeach
            </td>
        </tr>
        <tr><th>賞金総額</th><td>{{ $overviewPrizeTotal > 0 ? '¥' . number_format($overviewPrizeTotal) : '登録済み最終成績に準拠' }}</td></tr>
    </table>

    <div class="official-standard-overview-note">
        ※本書は確定・公開済みの大会情報、ゲーム別スコア、最終成績を一体で出力しています。
    </div>
</div>
