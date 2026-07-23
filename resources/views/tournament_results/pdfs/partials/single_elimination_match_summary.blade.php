@if (!empty($singleEliminationMatchSummary ?? []))
    @php
        $resolvedOfficialTitleClass = (string) ($officialTitleClass ?? view()->shared('officialTitleClass', ''));
    @endphp
    <div class="official-match-summary-page jpba-heavy">
        <h2 class="official-detail-page-title {{ $resolvedOfficialTitleClass }}">{{ $officialMainTitle }}</h2>
        <h3 class="official-detail-page-subtitle">決勝トーナメント 全対戦スコア</h3>

        <table class="official-match-summary-table">
            <thead>
                <tr>
                    <th class="match-col">対戦</th>
                    <th class="license-col">ライセンスNo.</th>
                    <th class="name-col">氏名</th>
                    <th class="games-col">ゲーム別スコア</th>
                    <th class="total-col">合計</th>
                    <th class="winner-col">結果</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($singleEliminationMatchSummary as $match)
                    @foreach (($match['players'] ?? []) as $playerIndex => $player)
                        <tr class="{{ $playerIndex === 0 ? 'match-start' : '' }} {{ !empty($player['is_winner']) ? 'winner-row' : '' }}">
                            @if ($playerIndex === 0)
                                <td rowspan="{{ count($match['players'] ?? []) }}">{{ $match['label'] ?? $match['code'] }}</td>
                            @endif
                            <td>{{ $player['license'] !== '' ? $player['license'] : '-' }}</td>
                            <td class="text-left">{{ $player['name'] }}</td>
                            <td>{{ implode(' / ', array_map(fn ($score) => number_format((int) $score), $player['scores'] ?? [])) }}</td>
                            <td>{{ number_format((int) ($player['total_pin'] ?? 0)) }}</td>
                            <td>{{ !empty($player['is_winner']) ? '勝' : '' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <div class="official-snapshot-note">
            ※登録済みの決勝トーナメント全対戦を、途中ラウンドを含めて出力しています。
        </div>
    </div>
@endif
