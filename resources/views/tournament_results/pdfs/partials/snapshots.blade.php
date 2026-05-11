@if (count($pdfScoreSnapshots) > 0)
    @foreach ($pdfScoreSnapshots as $snapshotSet)
        @php
            $snapshot = $snapshotSet['snapshot'] ?? null;
            $snapshotRows = collect($snapshotSet['rows'] ?? [])->values();
            $resultCode = trim((string) ($snapshot->result_code ?? ''));
            $isPrelimSnapshot = $resultCode === 'prelim_total';
            $isSemifinalSnapshot = $resultCode === 'semifinal_total';
            $targetStage = $isSemifinalSnapshot ? '準決勝' : '予選';
            $gameCount = $isSemifinalSnapshot ? 4 : 8;
        @endphp

        <div class="official-snapshot-page">
            <div class="official-snapshot-title jpba-extra-heavy">
                {{ $officialMainTitle }}<br>
                @if (($isSeasonTrialPdf ?? false) && trim($officialSeriesTitle . $officialSeasonTitle) !== '')
                    {{ $officialSeriesTitle }} {{ $officialSeasonTitle }}
                @endif
            </div>
            <div class="official-snapshot-subtitle jpba-heavy">
                {{ $snapshot ? $snapshotTitle($snapshot) : '大会成績' }}
                @if ($venueText !== '')
                    ／ {{ $venueText }}
                @endif
            </div>

            @if ($isSemifinalSnapshot)
                <table class="official-snapshot-table jpba-heavy">
                    <thead>
                        <tr>
                            @if ($isSeasonTrialPdf ?? false)
                                <th rowspan="2" class="snap-step-col">ｽﾃｯﾌﾟ<br>ﾎﾟｲﾝﾄ</th>
                            @endif
                            <th rowspan="2" class="snap-rank-col">順位</th>
                            <th rowspan="2" class="snap-license-col">ﾗｲｾﾝｽ<br>No.</th>
                            <th rowspan="2" class="snap-name-col">氏　名</th>
                            <th rowspan="2" class="snap-period-col">期</th>
                            <th rowspan="2" class="snap-arm-col">投</th>
                            <th rowspan="2" class="snap-belong-col">所　属<br>/ 用品契約</th>
                            <th rowspan="2" class="snap-wide-total-col">予選{{ $stageNumber($prelimGameCount) }}G</th>
                            <th rowspan="2" class="snap-avg-col">AVG</th>
                            <th rowspan="2" class="snap-rank-col">順位</th>
                            <th colspan="4">準　決　勝</th>
                            <th rowspan="2" class="snap-half-col">準決勝<br>{{ $gameCount }}G</th>
                            <th rowspan="2" class="snap-avg-col">AVG</th>
                            <th rowspan="2" class="snap-wide-total-col">通算{{ $stageNumber($semifinalTotalGameCount) }}G<br>T/PIN</th>
                            <th rowspan="2" class="snap-avg-col">AVG</th>
                        </tr>
                        <tr>
                            @for ($game = 1; $game <= $gameCount; $game++)
                                <th class="snap-game-col">{{ $game }}G</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshotRows as $index => $row)
                            @php
                                $rank = $snapshotValue($row, ['ranking'], '-');
                                $carryPin = $snapshotValue($row, ['carry_pin'], '');
                                $scratchPin = $snapshotValue($row, ['scratch_pin'], '');
                                $totalPin = $snapshotValue($row, ['total_pin'], '');
                                $average = $snapshotValue($row, ['average'], '');
                                $points = $snapshotValue($row, ['points'], '');
                                $semiAverage = ($scratchPin !== '' && is_numeric($scratchPin) && $gameCount > 0) ? ((float) $scratchPin / $gameCount) : null;
                                $prelimAverage = ($carryPin !== '' && is_numeric($carryPin) && ($prelimGameCount ?? 0) > 0) ? ((float) $carryPin / (int) $prelimGameCount) : null;
                                $prelimRank = $snapshotPrelimRank($row);
                                $belong = $snapshotBelong($row);
                                $stepPointLabel = $stepPointLabelForSemifinalRank($rank, $points);
                                $rankInt = is_numeric($rank) ? (int) $rank : 0;
                                $semifinalRowClasses = [];
                                if ($rankInt > 0 && $rankInt <= (int) ($finalQualifierCount ?: $semifinalQualifierCount ?: 8)) {
                                    $semifinalRowClasses[] = 'qualified-cell';
                                }
                                if ($rankInt === (int) ($finalQualifierCount ?: $semifinalQualifierCount ?: 8)) {
                                    $semifinalRowClasses[] = 'semifinal-finalist-border';
                                }
                            @endphp
                            <tr class="{{ implode(' ', $semifinalRowClasses) }}">
                                @if ($isSeasonTrialPdf ?? false)
                                    @if ($index === 0)
                                        <td rowspan="{{ (int) ($finalQualifierCount ?: $semifinalQualifierCount ?: 8) }}" class="finalist-ref-cell">入<br>賞<br>者<br>リ<br>ス<br>ト<br>参<br>照</td>
                                    @elseif ($index >= (int) ($finalQualifierCount ?: $semifinalQualifierCount ?: 8))
                                        <td class="step-point-cell">{{ $stepPointLabel }}</td>
                                    @endif
                                @endif
                                <td class="{{ $rankInt > 0 && $rankInt <= (int) ($finalQualifierCount ?: $semifinalQualifierCount ?: 8) ? 'qualified-cell' : '' }}">{{ $rank }}</td>
                                <td>{{ $snapshotLicense($row) }}</td>
                                <td class="text-left">{{ $snapshotName($row) }}</td>
                                <td>{{ $snapshotPeriod($row) }}</td>
                                <td>{{ $snapshotArm($row) }}</td>
                                <td class="{{ $snapshotBelongClass($belong) }}">{{ $belong }}</td>
                                <td>{{ $formatNumber($carryPin) }}</td>
                                <td>{{ $prelimAverage === null ? '-' : number_format($prelimAverage, 2) }}</td>
                                <td>{{ $prelimRank ?? '-' }}</td>
                                @for ($game = 1; $game <= $gameCount; $game++)
                                    @php $score = $snapshotScoreFor($targetStage, $row, $game); @endphp
                                    <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : $formatNumber($score) }}</td>
                                @endfor
                                <td>{{ $formatNumber($scratchPin) }}</td>
                                <td>{{ $semiAverage === null ? '-' : number_format($semiAverage, 2) }}</td>
                                <td>{{ $formatNumber($totalPin) }}</td>
                                <td>{{ $average === '' ? '-' : number_format((float) $average, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                @php
                    $prelimPreparedRows = [];
                    $firstHalfTotals = [];
                    foreach ($snapshotRows as $rowForHalf) {
                        $key = $snapshotLicenseKey($rowForHalf);
                        $firstHalf = 0;
                        $secondHalf = 0;
                        for ($game = 1; $game <= 4; $game++) {
                            $score = $snapshotScoreFor($targetStage, $rowForHalf, $game);
                            $firstHalf += is_numeric($score) ? (int) $score : 0;
                        }
                        for ($game = 5; $game <= 8; $game++) {
                            $score = $snapshotScoreFor($targetStage, $rowForHalf, $game);
                            $secondHalf += is_numeric($score) ? (int) $score : 0;
                        }
                        $firstHalfTotals[$key] = $firstHalf;
                        $prelimPreparedRows[] = [$rowForHalf, $firstHalf, $secondHalf, $key];
                    }
                    arsort($firstHalfTotals);
                    $firstHalfRankByKey = [];
                    $firstHalfRank = 1;
                    foreach ($firstHalfTotals as $key => $total) {
                        $firstHalfRankByKey[$key] = $firstHalfRank++;
                    }
                @endphp
                <table class="official-snapshot-table jpba-heavy">
                    <thead>
                        <tr>
                            <th rowspan="2" class="snap-rank-col">順位</th>
                            <th rowspan="2" class="snap-license-col">ﾗｲｾﾝｽ<br>No.</th>
                            <th rowspan="2" class="snap-name-col">氏　名</th>
                            <th rowspan="2" class="snap-period-col">期</th>
                            <th rowspan="2" class="snap-arm-col">投</th>
                            <th rowspan="2" class="snap-belong-col">所　属<br>/ 用品契約</th>
                            <th colspan="4">1G&nbsp;&nbsp;2G&nbsp;&nbsp;3G&nbsp;&nbsp;4G</th>
                            <th rowspan="2" class="snap-half-col">前半</th>
                            <th rowspan="2" class="snap-rank-col">順位</th>
                            <th colspan="4">5G&nbsp;&nbsp;6G&nbsp;&nbsp;7G&nbsp;&nbsp;8G</th>
                            <th rowspan="2" class="snap-half-col">後半</th>
                            <th rowspan="2" class="snap-wide-total-col">8G<br>T/PIN</th>
                            <th rowspan="2" class="snap-avg-col">AVG</th>
                        </tr>
                        <tr>
                            @for ($game = 1; $game <= 8; $game++)
                                <th class="snap-game-col">{{ $game }}G</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prelimPreparedRows as $prepared)
                            @php
                                [$row, $firstHalf, $secondHalf, $key] = $prepared;
                                $rank = $snapshotValue($row, ['ranking'], '-');
                                $totalPin = $snapshotValue($row, ['total_pin'], '');
                                $average = $snapshotValue($row, ['average'], '');
                                $belong = $snapshotBelong($row);
                                $rankInt = is_numeric($rank) ? (int) $rank : 0;
                                $isQualified = $rankInt > 0 && $rankInt <= (int) ($prelimQualifierCount ?: 0);
                                $prelimRowClasses = [];
                                if (($isSeasonTrialPdf ?? false) && $rankInt === 8) {
                                    $prelimRowClasses[] = 'prelim-top-eight-border';
                                }
                                if ($rankInt === (int) ($prelimQualifierCount ?: 0)) {
                                    $prelimRowClasses[] = 'prelim-qualified-border';
                                }
                            @endphp
                            <tr class="{{ implode(' ', $prelimRowClasses) }}">
                                <td class="{{ $isQualified ? 'qualified-cell' : '' }}">{{ $rank }}</td>
                                <td>{{ $snapshotLicense($row) }}</td>
                                <td class="text-left">{{ $snapshotName($row) }}</td>
                                <td>{{ $snapshotPeriod($row) }}</td>
                                <td>{{ $snapshotArm($row) }}</td>
                                <td class="{{ $snapshotBelongClass($belong) }}">{{ $belong }}</td>
                                @for ($game = 1; $game <= 4; $game++)
                                    @php $score = $snapshotScoreFor($targetStage, $row, $game); @endphp
                                    <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : $formatNumber($score) }}</td>
                                @endfor
                                <td>{{ $formatNumber($firstHalf) }}</td>
                                <td>{{ $firstHalfRankByKey[$key] ?? '-' }}</td>
                                @for ($game = 5; $game <= 8; $game++)
                                    @php $score = $snapshotScoreFor($targetStage, $row, $game); @endphp
                                    <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : $formatNumber($score) }}</td>
                                @endfor
                                <td>{{ $formatNumber($secondHalf) }}</td>
                                <td>{{ $formatNumber($totalPin) }}</td>
                                <td>{{ $average === '' ? '-' : number_format((float) $average, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="official-snapshot-note jpba-heavy">
                ※このページは正式反映済みスナップショットとゲーム別スコアをもとに出力しています。
            </div>
        </div>
    @endforeach
@endif
