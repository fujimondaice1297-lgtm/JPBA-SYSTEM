@php
    $overviewWeekdays = ['日', '月', '火', '水', '木', '金', '土'];
    $overviewFormatDate = function ($value, bool $includeYear) use ($overviewWeekdays): string {
        if (! $value) return '-';
        $date = \Illuminate\Support\Carbon::parse($value);
        $prefix = $includeYear
            ? $date->format('Y年') . '（令和' . ($date->year - 2018) . '年）'
            : '';
        return $prefix . $date->format('n月j日') . '（' . $overviewWeekdays[$date->dayOfWeek] . '）';
    };
    $overviewStart = $overviewFormatDate($tournament->start_date, true);
    $overviewEnd = $tournament->end_date
        ? $overviewFormatDate($tournament->end_date, false)
        : '';
    $overviewDate = $overviewStart . ($overviewEnd !== '' ? '～' . $overviewEnd : '');

    $overviewPrizeDistributions = collect($prizeDistributionMap ?? []);
    $overviewPrizeTotal = $overviewPrizeDistributions->isNotEmpty()
        ? $overviewPrizeDistributions->sum(fn ($amount): int => max(0, (int) $amount))
        : collect($resultRows ?? [])->sum(fn ($row): int => (int) ($row->prize_money ?? 0));

    $overviewImagePath = collect([
        $tournament->image_path ?? null,
        $tournament->hero_image_path ?? null,
        $tournament->title_logo_path ?? null,
        ...((array) ($tournament->poster_images ?? [])),
    ])->filter()->first();
    $overviewImageSrc = null;
    if ($overviewImagePath) {
        $overviewAbsolutePath = storage_path('app/public/' . ltrim((string) $overviewImagePath, '/\\'));
        if (is_file($overviewAbsolutePath) && is_readable($overviewAbsolutePath)) {
            $overviewMime = mime_content_type($overviewAbsolutePath) ?: 'image/jpeg';
            $overviewImageSrc = 'data:' . $overviewMime . ';base64,' . base64_encode((string) file_get_contents($overviewAbsolutePath));
        }
    }

    $overviewHost = trim((string) ($tournament->host ?? ''))
        ?: '公益社団法人日本プロボウリング協会';
    $overviewAuthorized = trim((string) ($tournament->authorized_by ?? ''))
        ?: '公益社団法人日本プロボウリング協会';
    $overviewVenue = trim((string) ($tournament->venue_name ?? '')) ?: $officialVenueTitle;
    $overviewParticipantCount = count($resultRows ?? []);
    $overviewRrCount = count((array) ($roundRobinPdf['players'] ?? []));
    $overviewTitleParts = preg_split('/プレゼンツ/u', $officialMainTitle, 2);
@endphp

<div class="standard-overview-page">
    <h1 class="standard-overview-title {{ $resolvedOfficialTitleClass ?? '' }}">
        {{ $overviewTitleParts[0] ?? $officialMainTitle }}@if (count($overviewTitleParts) > 1)プレゼンツ<br>{{ $overviewTitleParts[1] }}@endif
    </h1>

    <table class="standard-overview-layout">
        <tr>
            <td class="standard-overview-content">
                <table class="standard-overview-detail">
                    <tr><th>■ 主　　催</th><td>：{{ $overviewHost }}</td></tr>
                    <tr><th>■ 特別協賛</th><td>：{{ $tournament->special_sponsor ?: '-' }}</td></tr>
                    <tr><th>■ 後　　援</th><td>：{{ $tournament->support ?: '-' }}</td></tr>
                    <tr><th>■ 協　　賛</th><td>：{{ $tournament->sponsor ?: '-' }}</td></tr>
                    <tr><th>■ 協　　力</th><td>：{{ $overviewVenue }}</td></tr>
                    <tr><th>■ 主管運営</th><td>：{{ $tournament->supervisor ?: $overviewHost . ' 大会運営委員会' }}</td></tr>
                    <tr><th>■ 公　　認</th><td>：{{ $overviewAuthorized }}</td></tr>
                    <tr><th>■ 開催期日</th><td>：{{ $overviewDate }}</td></tr>
                    <tr>
                        <th>■ 会　　場</th>
                        <td>
                            ：{{ $overviewVenue }}<br>
                            <span class="standard-overview-indent">
                                {{ $tournament->venue_address ?: '' }}
                                @if ($tournament->venue_tel)　TEL {{ $tournament->venue_tel }}@endif
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>■ スケジュール</th>
                        <td>
                            ：予選{{ (int) ($prelimGameCount ?? 0) }}G
                            @if ((int) ($semifinalGameCount ?? 0) > 0)
                                ・準決勝{{ (int) $semifinalGameCount }}G
                            @endif
                            @if ($overviewRrCount > 0)
                                ・決勝ラウンドロビン{{ (int) ($roundRobinGameCount ?? 8) }}G
                                ・決勝ステップラダー
                            @endif
                            <div class="standard-overview-schedule">
                                予選：{{ $overviewParticipantCount }}名にて{{ (int) ($prelimGameCount ?? 0) }}Gを投球し、
                                上位{{ (int) ($prelimQualifierCount ?? 0) }}名を準決勝へ選出。<br>
                                準決勝：予選通過者が{{ (int) ($semifinalGameCount ?? 0) }}Gを投球し、
                                通算{{ (int) ($semifinalTotalGameCount ?? 0) }}G上位{{ $overviewRrCount ?: (int) ($finalQualifierCount ?? 0) }}名を決勝へ選出。<br>
                                @if ($overviewRrCount > 0)
                                    決勝RR：総当たり戦およびポジションマッチを行い、上位3名を決勝ステップラダーへ選出。<br>
                                    決勝：3名によるステップラダー方式で最終順位を決定。
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>■ 褒　　賞</th>
                        <td>：賞金総額 {{ $overviewPrizeTotal > 0 ? '￥' . number_format($overviewPrizeTotal) : ($tournament->prize ?: '-') }}</td>
                    </tr>
                </table>
            </td>
            @if ($overviewImageSrc)
                <td class="standard-overview-poster">
                    <img src="{!! $overviewImageSrc !!}" alt="">
                </td>
            @endif
        </tr>
    </table>
</div>
