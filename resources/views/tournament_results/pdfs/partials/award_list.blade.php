<div class="official-prize-title jpba-extra-heavy">〔入 賞 者 リ ス ト〕</div>

<table class="official-prize-table {{ ($isSeasonTrialPdf ?? false) ? '' : 'non-season-prize-table' }} jpba-heavy">
    <thead>
        <tr>
            <th class="rank-col">順位</th>
            <th class="license-col">ﾗｲｾﾝｽ<br>No.</th>
            <th class="name-col">氏　名</th>
            <th class="period-col">期</th>
            <th class="belong-col">所　属<br>/ 用品契約</th>
            @if ($isSeasonTrialPdf ?? false)
                <th class="total-point-col">獲得合計<br>ポイント</th>
                <th class="award-point-col">入賞<br>ﾎﾟｲﾝﾄ</th>
                <th class="step-point-col">ｽﾃｯﾌﾟ<br>ﾎﾟｲﾝﾄ</th>
            @endif
            <th class="prize-col">賞 金(¥)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($resultRows->take(($isSeasonTrialPdf ?? false) ? 8 : 30) as $result)
            @php
                $rankLabel = $resolveRank($result);
                $licenseNo = $resolveLicense($result);
                $name = $resolveName($result);
                $period = $resolvePeriod($result);
                $belong = $resolveBelong($result);
                $totalPoint = $resolveNumber($result, ['total_points', 'earned_total_points', 'earned_points', 'points'], 0);
                $awardPoint = $resolveNumber($result, ['award_points', 'prize_points', 'rank_points', 'entry_points'], null);
                $stepPoint = $resolveNumber($result, ['step_points', 'step_point', 'shootout_points', 'final_points'], null);
                $prizeMoney = $result->prize_money ?? null;
            @endphp
            <tr>
                <td class="nowrap">{{ $rankLabel }}</td>
                <td class="license-cell">{{ $licenseNo }}</td>
                <td class="text-left">{{ $name }}</td>
                <td class="nowrap">{{ $period }}</td>
                <td class="text-left belong-cell"><span class="{{ $belongTextClass($belong) }}">{{ $belong }}</span></td>
                @if ($isSeasonTrialPdf ?? false)
                    <td class="nowrap">{{ $formatNumber($totalPoint) }}</td>
                    <td class="nowrap">{{ $awardPoint === null ? '-' : $formatNumber($awardPoint) }}</td>
                    <td class="nowrap">{{ $stepPoint === null ? '-' : $formatNumber($stepPoint) }}</td>
                @endif
                <td class="text-right nowrap">{{ $formatPrize($prizeMoney) }}</td>
            </tr>
        @endforeach

        <tr>
            <td class="nowrap">以上</td>
            <td colspan="{{ ($isSeasonTrialPdf ?? false) ? 8 : 5 }}" class="text-left">決勝（{{ $finalFormatLabel }}）　※賞金獲得者</td>
        </tr>
    </tbody>
</table>
