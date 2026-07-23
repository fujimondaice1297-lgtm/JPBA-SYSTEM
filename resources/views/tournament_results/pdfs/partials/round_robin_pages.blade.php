@php
    $rrPayload = isset($roundRobinPdf) && is_array($roundRobinPdf) ? $roundRobinPdf : [];
    $rrPlayers = array_values((array) ($rrPayload['players'] ?? []));
    $rrMeta = (array) ($rrPayload['meta'] ?? []);
    $rrRounds = array_values((array) ($rrPayload['rounds'] ?? []));
    $rrPlayersBySeed = [];
    foreach ($rrPlayers as $rrPlayer) {
        $rrPlayersBySeed[(int) ($rrPlayer['seed'] ?? 0)] = (array) $rrPlayer;
    }
    $rrMatrixSeedOrder = count($rrPlayers) === 8
        ? [2, 3, 8, 7, 6, 5, 4, 1]
        : array_values(array_filter(array_map(
            fn ($rrPlayer): int => (int) ($rrPlayer['seed'] ?? 0),
            $rrPlayers
        )));
    $rrMatrixPlayers = array_values(array_filter(array_map(
        fn (int $seed): array => (array) ($rrPlayersBySeed[$seed] ?? []),
        $rrMatrixSeedOrder
    )));
    $rrPairForSeeds = static function (int $game, int $leftSeed, int $rightSeed) use ($rrRounds): ?array {
        foreach ((array) ($rrRounds[$game - 1] ?? []) as $pair) {
            $pair = (array) $pair;
            $pairLeft = (int) ($pair['left_seed'] ?? 0);
            $pairRight = (int) ($pair['right_seed'] ?? 0);
            if (($pairLeft === $leftSeed && $pairRight === $rightSeed)
                || ($pairLeft === $rightSeed && $pairRight === $leftSeed)
            ) {
                return $pair;
            }
        }

        return null;
    };
    $rrGameCountForPdf = max(1, (int) ($rrMeta['round_robin_games'] ?? count($rrRounds)));
    $rrCarryGamesForPdf = count($rrPlayers) > 0 ? (int) ($rrPlayers[0]['carry_games'] ?? 0) : 0;
    $roundRobinPageMode = trim((string) ($roundRobinPageMode ?? 'all'));
@endphp

@if (count($rrPlayers) > 0)
    @if ($roundRobinPageMode !== 'matches')
    <div class="official-round-robin-page jpba-heavy">
        <h2 class="official-snapshot-title {{ $resolvedOfficialTitleClass ?? '' }}">{{ $officialMainTitle }}</h2>
        <h3 class="official-snapshot-subtitle">決勝ラウンドロビン 順位表 ／ {{ $officialVenueTitle }}</h3>

        <table class="official-round-robin-ranking-table">
            <thead>
                <tr>
                    <th rowspan="2">RR<br>順位</th>
                    <th rowspan="2" class="rr-license-col">ﾗｲｾﾝｽ<br>No.</th>
                    <th rowspan="2" class="rr-name-col">氏　名</th>
                    <th rowspan="2">期</th>
                    <th rowspan="2" class="rr-belong-col">所　属 / 用品契約</th>
                    <th rowspan="2">ﾀｲﾄﾙ</th>
                    <th rowspan="2">予選<br>順位</th>
                    <th rowspan="2">準決勝<br>順位</th>
                    <th rowspan="2">持込<br>{{ $rrCarryGamesForPdf }}G</th>
                    <th colspan="{{ $rrGameCountForPdf }}">各ゲーム終了時順位</th>
                    <th rowspan="2">RR<br>T/PIN</th>
                    <th rowspan="2">RR<br>AVG</th>
                    <th rowspan="2">通算<br>T/PIN</th>
                    <th rowspan="2">通算<br>AVG</th>
                    <th rowspan="2">Bonus</th>
                    <th rowspan="2">勝-負-分</th>
                    <th rowspan="2">Total<br>Point</th>
                </tr>
                <tr>
                    @for ($game = 1; $game <= $rrGameCountForPdf; $game++)
                        <th>{{ $game }}G</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach ($rrPlayers as $rrPlayer)
                    @php
                        $rrPlayer = (array) $rrPlayer;
                        $rrInfo = $snapshotInfo($rrPlayer);
                        $rrTitleCount = (int) $infoValue($rrInfo, ['official_title_count', 'title_count', 'titles_count'], 0);
                        if ((int) ($rrPlayer['rank'] ?? 0) === 1) {
                            $rrTitleCount = max(0, $rrTitleCount - 1);
                        }
                        $rrOverallGames = (int) ($rrPlayer['carry_games'] ?? 0) + count((array) ($rrPlayer['rr_scores'] ?? []));
                        $rrTotalPoint = (int) ($rrPlayer['overall_total_points'] ?? 0) - ($rrOverallGames * 200);
                        $rrBelong = $snapshotBelong($rrPlayer);
                    @endphp
                    <tr>
                        <td>{{ $rrPlayer['rank'] ?? '-' }}</td>
                        <td class="pdf-license-cell">{{ $snapshotLicense($rrPlayer) }}</td>
                        <td class="text-left">{{ $snapshotName($rrPlayer) }}</td>
                        <td>{{ $snapshotPeriod($rrPlayer) }}</td>
                        <td class="rr-belong-cell"><span class="{{ $snapshotBelongClass($rrBelong) }}">{{ $rrBelong }}</span></td>
                        <td>{{ $rrTitleCount }}</td>
                        <td>{{ $snapshotPrelimRank($rrPlayer) ?? '-' }}</td>
                        <td>{{ $rrPlayer['seed'] ?? '-' }}</td>
                        <td>{{ $formatNumber($rrPlayer['carry_pin'] ?? 0) }}</td>
                        @for ($game = 1; $game <= $rrGameCountForPdf; $game++)
                            <td>{{ $rrPlayer['rank_history'][$game] ?? '-' }}</td>
                        @endfor
                        <td>{{ $formatNumber($rrPlayer['rr_total_pin'] ?? 0) }}</td>
                        <td>{{ isset($rrPlayer['rr_average']) ? number_format((float) $rrPlayer['rr_average'], 2) : '-' }}</td>
                        <td>{{ $formatNumber(($rrPlayer['carry_pin'] ?? 0) + ($rrPlayer['rr_total_pin'] ?? 0)) }}</td>
                        <td>{{ $rrOverallGames > 0 ? number_format((($rrPlayer['carry_pin'] ?? 0) + ($rrPlayer['rr_total_pin'] ?? 0)) / $rrOverallGames, 2) : '-' }}</td>
                        <td>{{ $formatNumber($rrPlayer['bonus_points'] ?? 0) }}</td>
                        <td>{{ $rrPlayer['record'] ?? '-' }}</td>
                        <td>{{ $rrTotalPoint >= 0 ? '+' : '' }}{{ $formatNumber($rrTotalPoint) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="official-snapshot-note">※勝者30ポイント、引分15ポイントを加算。最終ゲームはポジションマッチです。</div>
    </div>
    @endif

    @if ($roundRobinPageMode !== 'ranking')
    <div class="official-round-robin-page jpba-heavy">
        <h2 class="official-round-robin-match-title">ラウンドロビン対戦表</h2>

        <table class="official-round-robin-match-table">
            <thead>
                <tr>
                    <th>氏　名</th>
                    <th>スコア</th>
                    <th>通過<br>順位</th>
                    @foreach ($rrMatrixPlayers as $rrHeaderPlayer)
                        <th>{{ $snapshotName($rrHeaderPlayer) }}</th>
                    @endforeach
                    <th>PM<br>対戦者</th>
                    <th>PM<br>スコア</th>
                    <th>R・R<br>T/PIN</th>
                    <th>WON<br>POINT</th>
                    <th>Grand<br>Total</th>
                    <th>Total<br>Point</th>
                    <th>順位</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rrMatrixPlayers as $rrPlayer)
                    @php
                        $rrPlayer = (array) $rrPlayer;
                        $rrSeed = (int) ($rrPlayer['seed'] ?? 0);
                        $rrOverallGames = (int) ($rrPlayer['carry_games'] ?? 0) + count((array) ($rrPlayer['rr_scores'] ?? []));
                        $rrTotalPoint = (int) ($rrPlayer['overall_total_points'] ?? 0) - ($rrOverallGames * 200);
                        $rrPositionPair = collect($rrRounds[$rrGameCountForPdf - 1] ?? [])->first(
                            fn ($pair): bool => (int) ($pair['left_seed'] ?? 0) === $rrSeed
                                || (int) ($pair['right_seed'] ?? 0) === $rrSeed
                        );
                        $rrPositionOpponentSeed = $rrPositionPair
                            ? ((int) ($rrPositionPair['left_seed'] ?? 0) === $rrSeed
                                ? (int) ($rrPositionPair['right_seed'] ?? 0)
                                : (int) ($rrPositionPair['left_seed'] ?? 0))
                            : 0;
                        $rrPositionOpponent = (array) ($rrPlayersBySeed[$rrPositionOpponentSeed] ?? []);
                        $rrPositionScore = $rrPlayer['rr_scores'][$rrGameCountForPdf] ?? null;
                        $rrPositionOpponentScore = $rrPositionOpponent['rr_scores'][$rrGameCountForPdf] ?? null;
                        $rrPositionBonus = $rrPositionScore !== null && $rrPositionOpponentScore !== null
                            ? ($rrPositionScore > $rrPositionOpponentScore
                                ? (int) ($rrMeta['win_bonus'] ?? 30)
                                : ($rrPositionScore === $rrPositionOpponentScore
                                    ? (int) ($rrMeta['tie_bonus'] ?? 15)
                                    : 0))
                            : 0;
                    @endphp
                    <tr>
                        <td class="text-left">{{ $snapshotName($rrPlayer) }}</td>
                        <td>{{ $formatNumber($rrPlayer['carry_pin'] ?? 0) }}</td>
                        <td>{{ $rrSeed }}</td>
                        @foreach ($rrMatrixPlayers as $rrOpponent)
                            @php
                                $rrOpponent = (array) $rrOpponent;
                                $rrOpponentSeed = (int) ($rrOpponent['seed'] ?? 0);
                                $rrHeadToHeadGame = null;
                                for ($game = 1; $game < $rrGameCountForPdf; $game++) {
                                    if ($rrPairForSeeds($game, $rrSeed, $rrOpponentSeed) !== null) {
                                        $rrHeadToHeadGame = $game;
                                        break;
                                    }
                                }
                                $rrSelfScore = $rrHeadToHeadGame === null
                                    ? null
                                    : ($rrPlayer['rr_scores'][$rrHeadToHeadGame] ?? null);
                                $rrOpponentScore = $rrHeadToHeadGame === null
                                    ? null
                                    : ($rrOpponent['rr_scores'][$rrHeadToHeadGame] ?? null);
                                $rrBonus = $rrSelfScore !== null && $rrOpponentScore !== null
                                    ? ($rrSelfScore > $rrOpponentScore
                                        ? (int) ($rrMeta['win_bonus'] ?? 30)
                                        : ($rrSelfScore === $rrOpponentScore
                                            ? (int) ($rrMeta['tie_bonus'] ?? 15)
                                            : 0))
                                    : 0;
                            @endphp
                            @if ($rrSeed === $rrOpponentSeed)
                                <td class="rr-matrix-self"></td>
                            @else
                                <td class="rr-match-cell">
                                    <span class="rr-matrix-game">{{ $rrHeadToHeadGame ?? '-' }}</span>
                                    <span class="rr-matrix-score {{ $scoreTextClass($rrSelfScore) }}">{{ $rrSelfScore ?? '-' }}</span>
                                    <span class="rr-match-bonus">{{ $rrBonus }}</span>
                                </td>
                            @endif
                        @endforeach
                        <td>{{ $snapshotName($rrPositionOpponent) }}</td>
                        <td class="rr-match-cell">
                            <span class="rr-matrix-score {{ $scoreTextClass($rrPositionScore) }}">{{ $rrPositionScore ?? '-' }}</span>
                            <span class="rr-match-bonus">{{ $rrPositionBonus }}</span>
                        </td>
                        <td>{{ $formatNumber($rrPlayer['rr_total_pin'] ?? 0) }}</td>
                        <td>{{ $formatNumber($rrPlayer['bonus_points'] ?? 0) }}</td>
                        <td>{{ $formatNumber(($rrPlayer['carry_pin'] ?? 0) + ($rrPlayer['rr_total_pin'] ?? 0)) }}</td>
                        <td>{{ $rrTotalPoint >= 0 ? '+' : '' }}{{ $formatNumber($rrTotalPoint) }}</td>
                        <td>{{ $rrPlayer['rank'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="official-round-robin-footer">（公社）日本プロボウリング協会</div>
    </div>
    @endif
@endif
