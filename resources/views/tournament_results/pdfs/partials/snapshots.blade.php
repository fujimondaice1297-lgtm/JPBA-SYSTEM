@if (count($pdfScoreSnapshots) > 0)
    @foreach ($pdfScoreSnapshots as $snapshotSet)
        @php
            $snapshot = $snapshotSet['snapshot'] ?? null;
            $snapshotRows = collect($snapshotSet['rows'] ?? [])->values();
            $resultCode = trim((string) ($snapshot->result_code ?? ''));
            $isPrelimSnapshot = $resultCode === 'prelim_total';
            $isSemifinalSnapshot = $resultCode === 'semifinal_total';
            $isRoundRobinSnapshot = $resultCode === 'round_robin_total' || trim((string) ($snapshot->result_type ?? '')) === 'round_robin';
            $targetStage = $isRoundRobinSnapshot ? 'ラウンドロビン' : ($isSemifinalSnapshot ? '準決勝' : '予選');
            if ($isRoundRobinSnapshot) {
                $gameCount = (int) (($snapshot->games_count ?? 0) - ($snapshot->carry_game_count ?? 0));
                if ($gameCount <= 0) {
                    $gameCount = (int) ($roundRobinGameCount ?? 8);
                }
            } else {
                $gameCount = $isSemifinalSnapshot ? (int) ($semifinalGameCount ?: 4) : (int) ($prelimGameCount ?: ($snapshot->games_count ?? 8));
            }
            $gameCount = max(1, $gameCount);

            if ($isSemifinalSnapshot) {
                // 準決勝通算成績は、準決勝を実際に投球した選手だけを表示する。
                // 予選落ち選手をここに残すと、準決勝スコア 0 / 通算20G 欄に予選16Gだけが並び、
                // 「準決勝成績」として誤解を招くため除外する。
                $snapshotRows = $snapshotRows->filter(function ($row) use ($snapshotValue, $snapshotScoreFor, $targetStage, $gameCount): bool {
                    $scratchPin = $snapshotValue($row, ['scratch_pin'], null);
                    if (is_numeric($scratchPin) && (int) $scratchPin > 0) {
                        return true;
                    }

                    for ($game = 1; $game <= $gameCount; $game++) {
                        $score = $snapshotScoreFor($targetStage, $row, $game);
                        if (is_numeric($score) && (int) $score > 0) {
                            return true;
                        }
                    }

                    return false;
                })->values();
            }

            $title = $snapshotTitle($snapshot);

            $sumScores = function ($row, array $games) use ($snapshotScoreFor, $targetStage): int {
                $total = 0;
                foreach ($games as $game) {
                    $score = $snapshotScoreFor($targetStage, $row, (int) $game);
                    if (is_numeric($score)) {
                        $total += (int) $score;
                    }
                }
                return $total;
            };

            $rankMapForGames = function (array $games) use ($snapshotRows, $sumScores, $snapshotValue): array {
                $items = [];
                foreach ($snapshotRows as $rowForRank) {
                    $total = $sumScores($rowForRank, $games);
                    $rowId = (int) ($rowForRank->id ?? 0);
                    $ranking = $snapshotValue($rowForRank, ['ranking'], 999999);
                    $items[] = [
                        'id' => $rowId,
                        'total' => $total,
                        'ranking' => is_numeric($ranking) ? (int) $ranking : 999999,
                    ];
                }
                usort($items, fn ($a, $b) => ($b['total'] <=> $a['total']) ?: ($a['ranking'] <=> $b['ranking']));
                $rankMap = [];
                $rank = 0;
                $previous = null;
                foreach ($items as $index => $item) {
                    if ($previous === null || $item['total'] !== $previous) {
                        $rank = $index + 1;
                        $previous = $item['total'];
                    }
                    $rankMap[$item['id']] = $rank;
                }
                return $rankMap;
            };

            $snapshotNameCellStyle = function (string $name): string {
                // 中黒を含む外国名は途中改行でセル高が広がりやすいので、PDFでは1行固定にする。
                return preg_match('/[・･]/u', $name) ? 'white-space: nowrap; font-size: 7px;' : '';
            };

            $semifinalAdvanceCountForPdf = (int) ($semifinalQualifierCount ?: 8);
        @endphp

        <div class="official-snapshot-page {{ $isPrelimSnapshot ? 'official-snapshot-page-prelim' : ($isRoundRobinSnapshot ? 'official-snapshot-page-round-robin' : 'official-snapshot-page-semifinal') }} jpba-heavy">
            <h2 class="official-snapshot-title jpba-heavy">{{ $officialMainTitle }}</h2>
            <h3 class="official-snapshot-subtitle jpba-heavy">{{ $title }} ／ {{ $officialVenueTitle }}</h3>

            @if ($isRoundRobinSnapshot)
                <table class="official-snapshot-table jpba-heavy">
                    <thead>
                        <tr>
                            <th rowspan="2" class="snap-rank-col">順位</th>
                            <th rowspan="2" class="snap-license-col">ﾗｲｾﾝｽ<br>No.</th>
                            <th rowspan="2" class="snap-name-col">氏　名</th>
                            <th rowspan="2" class="snap-period-col">期</th>
                            <th rowspan="2" class="snap-arm-col">投</th>
                            <th rowspan="2" class="snap-belong-col">所　属<br>/ 用品契約</th>
                            <th rowspan="2" class="snap-wide-total-col">持込{{ (int) ($snapshot->carry_game_count ?? 0) }}G</th>
                            <th colspan="{{ $gameCount }}">ラウンドロビン</th>
                            <th rowspan="2" class="snap-wide-total-col">RR<br>{{ $gameCount }}G</th>
                            <th rowspan="2" class="snap-half-col">Bonus</th>
                            <th rowspan="2" class="snap-wide-total-col">RR合計</th>
                            <th rowspan="2" class="snap-wide-total-col">TOTAL<br>POINT</th>
                            <th rowspan="2" class="snap-avg-col">AVG</th>
                        </tr>
                        <tr>
                            @for ($game = 1; $game <= $gameCount; $game++)
                                <th class="snap-game-col">{{ $game }}G</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshotRows as $row)
                            @php
                                $rank = $snapshotValue($row, ['ranking'], '-');
                                $carryPin = $snapshotValue($row, ['carry_pin'], 0);
                                $scratchPin = $snapshotValue($row, ['scratch_pin'], 0);
                                $bonusPoint = $snapshotValue($row, ['points'], 0);
                                $totalPin = $snapshotValue($row, ['total_pin'], 0);
                                $totalPoint = $snapshotValue($row, ['tie_break_value'], '');
                                if ($totalPoint === '' || $totalPoint === null) {
                                    $totalPoint = (int) $totalPin + (int) $bonusPoint;
                                }
                                $average = $snapshotValue($row, ['average'], '');
                                $belong = $snapshotBelong($row);
                                $rankInt = is_numeric($rank) ? (int) $rank : 0;
                                $roundRobinRowClasses = [];
                                if ($rankInt > 0 && $rankInt <= (int) ($finalQualifierCount ?: 3)) {
                                    $roundRobinRowClasses[] = 'qualified-cell';
                                }
                            @endphp
                            <tr class="{{ implode(' ', $roundRobinRowClasses) }}">
                                <td class="{{ $rankInt > 0 && $rankInt <= (int) ($finalQualifierCount ?: 3) ? 'qualified-cell' : '' }}">{{ $rank }}</td>
                                <td>{{ $snapshotLicense($row) }}</td>
                                <td class="text-left" style="{{ $snapshotNameCellStyle($snapshotName($row)) }}">{{ $snapshotName($row) }}</td>
                                <td>{{ $snapshotPeriod($row) }}</td>
                                <td>{{ $snapshotArm($row) }}</td>
                                <td class="{{ $snapshotBelongClass($belong) }}">{{ $belong }}</td>
                                <td>{{ $formatNumber($carryPin) }}</td>
                                @for ($game = 1; $game <= $gameCount; $game++)
                                    @php $score = $snapshotScoreFor($targetStage, $row, $game); @endphp
                                    <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : $formatNumber($score) }}</td>
                                @endfor
                                <td>{{ $formatNumber($scratchPin) }}</td>
                                <td>{{ $formatNumber($bonusPoint) }}</td>
                                <td>{{ $formatNumber((int) $scratchPin + (int) $bonusPoint) }}</td>
                                <td>{{ $formatNumber($totalPoint) }}</td>
                                <td>{{ $average === '' ? '-' : number_format((float) $average, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif ($isSemifinalSnapshot)
                <table class="official-snapshot-table jpba-heavy">
                    <thead>
                        <tr>
                            @if ($isSeasonTrialPdf ?? false)
                                <th rowspan="2" class="snap-step-col">&nbsp;</th>
                            @endif
                            <th rowspan="2" class="snap-rank-col">順位</th>
                            <th rowspan="2" class="snap-license-col">ﾗｲｾﾝｽ<br>No.</th>
                            <th rowspan="2" class="snap-name-col">氏　名</th>
                            <th rowspan="2" class="snap-period-col">期</th>
                            <th rowspan="2" class="snap-arm-col">投</th>
                            <th rowspan="2" class="snap-belong-col">所　属<br>/ 用品契約</th>
                            <th rowspan="2" class="snap-wide-total-col">予選{{ $prelimGameCount }}G</th>
                            <th rowspan="2" class="snap-avg-col">AVG</th>
                            <th rowspan="2" class="snap-rank-col">順位</th>
                            <th colspan="{{ $gameCount + 2 }}">準　決　勝</th>
                            <th rowspan="2" class="snap-wide-total-col">通算{{ $semifinalTotalGameCount }}G<br>T/PIN</th>
                            <th rowspan="2" class="snap-avg-col">AVG</th>
                        </tr>
                        <tr>
                            @for ($game = 1; $game <= $gameCount; $game++)
                                <th class="snap-game-col">{{ $game }}G</th>
                            @endfor
                            <th class="snap-half-col">準決勝<br>{{ $gameCount }}G</th>
                            <th class="snap-avg-col">AVG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshotRows as $index => $row)
                            @php
                                $rank = $snapshotValue($row, ['ranking'], '-');
                                $carryPin = $snapshotValue($row, ['carry_pin'], 0);
                                $scratchPin = $snapshotValue($row, ['scratch_pin'], 0);
                                $totalPin = $snapshotValue($row, ['total_pin'], 0);
                                $average = $snapshotValue($row, ['average'], '');
                                $prelimAverage = ((int) ($prelimGameCount ?: 0)) > 0 ? ((int) $carryPin / (int) $prelimGameCount) : null;
                                $semiAverage = $gameCount > 0 ? ((int) $scratchPin / $gameCount) : null;
                                $prelimRank = $snapshotPrelimRank($row);
                                $belong = $snapshotBelong($row);
                                $rankInt = is_numeric($rank) ? (int) $rank : 0;
                                $stepPointLabel = ($isSeasonTrialPdf ?? false) ? $stepPointLabelForSemifinalRank($rank, $snapshotValue($row, ['points'], null)) : null;
                                $semifinalRowClasses = [];
                                if ($rankInt > 0 && $rankInt <= $semifinalAdvanceCountForPdf) {
                                    $semifinalRowClasses[] = 'qualified-cell';
                                }
                                if ($rankInt === $semifinalAdvanceCountForPdf) {
                                    $semifinalRowClasses[] = 'semifinal-finalist-border';
                                }
                            @endphp
                            <tr class="{{ implode(' ', $semifinalRowClasses) }}">
                                @if ($isSeasonTrialPdf ?? false)
                                    @if ($index === 0)
                                        <td rowspan="{{ $semifinalAdvanceCountForPdf }}" class="finalist-ref-cell">入<br>賞<br>者<br>リ<br>ス<br>ト<br>参<br>照</td>
                                    @elseif ($index >= $semifinalAdvanceCountForPdf)
                                        <td class="step-point-cell">{{ $stepPointLabel }}</td>
                                    @endif
                                @endif
                                <td class="{{ $rankInt > 0 && $rankInt <= $semifinalAdvanceCountForPdf ? 'qualified-cell' : '' }}">{{ $rank }}</td>
                                <td>{{ $snapshotLicense($row) }}</td>
                                <td class="text-left" style="{{ $snapshotNameCellStyle($snapshotName($row)) }}">{{ $snapshotName($row) }}</td>
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
                    $prelimTotalGames = max(8, (int) ($snapshot->games_count ?? $prelimGameCount ?? 8));
                    $prelimFirstGames = range(1, min(8, $prelimTotalGames));
                    $prelimDetailStart = $prelimTotalGames > 8 ? 9 : 1;
                    $prelimDetailGames = range($prelimDetailStart, $prelimTotalGames);
                    $prelimFirstRankMap = $rankMapForGames($prelimFirstGames);
                    $prelimDetailRankMap = $rankMapForGames($prelimDetailGames);
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
                            @if ($prelimTotalGames > 8)
                                <th colspan="3">前半8G</th>
                                <th colspan="{{ count($prelimDetailGames) + 3 }}">後半{{ count($prelimDetailGames) }}G</th>
                            @else
                                <th colspan="{{ count($prelimDetailGames) + 3 }}">予選{{ $prelimTotalGames }}G</th>
                            @endif
                            <th rowspan="2" class="snap-wide-total-col">{{ $prelimTotalGames }}G<br>T/PIN</th>
                            <th rowspan="2" class="snap-avg-col">AVG</th>
                        </tr>
                        <tr>
                            @if ($prelimTotalGames > 8)
                                <th class="snap-half-col">T/PIN</th>
                                <th class="snap-avg-col">AVG</th>
                                <th class="snap-rank-col">順位</th>
                            @endif
                            @foreach ($prelimDetailGames as $game)
                                <th class="snap-game-col">{{ $game }}G</th>
                            @endforeach
                            <th class="snap-half-col">T/PIN</th>
                            <th class="snap-avg-col">AVG</th>
                            <th class="snap-rank-col">順位</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshotRows as $row)
                            @php
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
                                $rowId = (int) ($row->id ?? 0);
                                $firstTotal = $sumScores($row, $prelimFirstGames);
                                $detailTotal = $sumScores($row, $prelimDetailGames);
                                $firstAvg = count($prelimFirstGames) > 0 ? $firstTotal / count($prelimFirstGames) : null;
                                $detailAvg = count($prelimDetailGames) > 0 ? $detailTotal / count($prelimDetailGames) : null;
                            @endphp
                            <tr class="{{ implode(' ', $prelimRowClasses) }}">
                                <td class="{{ $isQualified ? 'qualified-cell' : '' }}">{{ $rank }}</td>
                                <td>{{ $snapshotLicense($row) }}</td>
                                <td class="text-left" style="{{ $snapshotNameCellStyle($snapshotName($row)) }}">{{ $snapshotName($row) }}</td>
                                <td>{{ $snapshotPeriod($row) }}</td>
                                <td>{{ $snapshotArm($row) }}</td>
                                <td class="{{ $snapshotBelongClass($belong) }}">{{ $belong }}</td>
                                @if ($prelimTotalGames > 8)
                                    <td>{{ $formatNumber($firstTotal) }}</td>
                                    <td>{{ $firstAvg === null ? '-' : number_format($firstAvg, 2) }}</td>
                                    <td>{{ $prelimFirstRankMap[$rowId] ?? '-' }}</td>
                                @endif
                                @foreach ($prelimDetailGames as $game)
                                    @php $score = $snapshotScoreFor($targetStage, $row, $game); @endphp
                                    <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : $formatNumber($score) }}</td>
                                @endforeach
                                <td>{{ $formatNumber($detailTotal) }}</td>
                                <td>{{ $detailAvg === null ? '-' : number_format($detailAvg, 2) }}</td>
                                <td>{{ $prelimDetailRankMap[$rowId] ?? '-' }}</td>
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
