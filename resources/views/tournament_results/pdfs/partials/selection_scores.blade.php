@php
    $resolvedOfficialTitleClass = (string) ($officialTitleClass ?? view()->shared('officialTitleClass', ''));
@endphp
@foreach (($selectionScoreSections ?? []) as $section)
    <div class="official-selection-score-page jpba-heavy">
        <h2 class="official-detail-page-title {{ $resolvedOfficialTitleClass }}">{{ $officialMainTitle }}</h2>
        <h3 class="official-detail-page-subtitle">{{ $section['stage'] }} 3G成績</h3>

        <table class="official-selection-score-table">
            <thead>
                <tr>
                    <th class="rank-col">順位</th>
                    <th class="license-col">ライセンスNo.</th>
                    <th class="name-col">氏名</th>
                    <th class="game-col">1G</th>
                    <th class="game-col">2G</th>
                    <th class="game-col">3G</th>
                    <th class="total-col">3G T/PIN</th>
                    <th class="avg-col">AVG</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($section['rows'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['ranking'] }}</td>
                        <td class="pdf-license-cell">{{ $row['license'] !== '' ? $row['license'] : '-' }}</td>
                        <td class="text-left">{{ $row['name'] }}</td>
                        @for ($game = 1; $game <= 3; $game++)
                            @php $score = $row['scores'][$game] ?? null; @endphp
                            <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : number_format((int) $score) }}</td>
                        @endfor
                        <td>{{ number_format((int) $row['total_pin']) }}</td>
                        <td>{{ number_format((float) $row['average'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="official-snapshot-note">
            ※各シフトの登録済みゲーム別スコアをもとに順位・合計・アベレージを出力しています。
        </div>
    </div>
@endforeach
