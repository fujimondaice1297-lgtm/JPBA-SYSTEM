@php
    $standardStep = (array) ($stepLadderPdf ?? []);
    $standardStepSeeds = array_values((array) ($standardStep['seeds'] ?? []));
    $standardStepSemi = (array) ($standardStep['semifinal'] ?? []);
    $standardStepFinal = (array) ($standardStep['final'] ?? []);

    $standardStepName = function ($player): string {
        $player = (array) ($player ?? []);
        return trim((string) (
            $player['display_name']
            ?? $player['name']
            ?? $player['amateur_name']
            ?? '-'
        ));
    };
    $standardStepScore = fn ($value): string => $value === null || $value === '' ? '-' : number_format((int) $value);

    $standardSeed1 = (array) ($standardStepSeeds[0] ?? []);
    $standardSeed2 = (array) ($standardStepSeeds[1] ?? []);
    $standardSeed3 = (array) ($standardStepSeeds[2] ?? []);
    $standardSemiWinner = (array) ($standardStepSemi['winner'] ?? []);
    $standardFinalWinner = (array) ($standardStepFinal['winner'] ?? []);
    $standardScoreImages = array_values((array) ($matchScoreSheetImages ?? []));
    usort($standardScoreImages, function (array $left, array $right): int {
        $priority = static function (array $scoreImage): int {
            $label = trim((string) ($scoreImage['match_label'] ?? ''));
            if (str_contains($label, '優勝') || str_contains($label, 'FINAL')) return 0;
            if (str_contains($label, '3位') || str_contains($label, 'R1')) return 1;
            return 2;
        };

        return ($priority($left) <=> $priority($right))
            ?: ((int) ($left['game_number'] ?? 0) <=> (int) ($right['game_number'] ?? 0));
    });
    $standardStepTitleParts = preg_split('/プレゼンツ/u', $officialMainTitle, 2);
@endphp

<div class="standard-step-page">
    <h1 class="standard-step-title {{ $resolvedOfficialTitleClass ?? '' }}">
        {{ $standardStepTitleParts[0] ?? $officialMainTitle }}@if (count($standardStepTitleParts) > 1)プレゼンツ<br>{{ $standardStepTitleParts[1] }}@endif
    </h1>
    <h2>決勝（3名によるステップラダー方式）</h2>

    <table class="standard-step-layout">
        <tr>
            <td class="standard-step-bracket-cell">
                <div class="standard-step-final-rank">最終順位</div>

                <table class="standard-step-bracket-table">
                    <tr>
                        <td class="standard-step-seed-label">1位通過</td>
                        <td class="standard-step-player">{{ $standardStepName($standardSeed1) }}</td>
                        <td class="standard-step-score">{{ $standardStepScore($standardStepFinal['top_score'] ?? null) }}</td>
                        <td rowspan="2" class="standard-step-champion">
                            <span>優勝</span>
                            <strong>{{ $standardStepName($standardFinalWinner) }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td class="standard-step-seed-label">2位通過</td>
                        <td class="standard-step-player">{{ $standardStepName($standardSeed2) }}</td>
                        <td class="standard-step-score">{{ $standardStepScore($standardStepSemi['top_score'] ?? null) }}</td>
                    </tr>
                    <tr>
                        <td class="standard-step-seed-label">3位通過</td>
                        <td class="standard-step-player">{{ $standardStepName($standardSeed3) }}</td>
                        <td class="standard-step-score">{{ $standardStepScore($standardStepSemi['bottom_score'] ?? null) }}</td>
                        <td class="standard-step-advance">
                            {{ $standardStepName($standardSemiWinner) }}
                            <strong>{{ $standardStepScore($standardStepFinal['bottom_score'] ?? null) }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="standard-step-summary-cell">
                <div class="standard-step-winner-title">優勝</div>
                <div class="standard-step-winner-name">{{ $standardStepName($standardFinalWinner) }}</div>
                <div class="standard-step-winner-note">大会優勝者</div>
            </td>
        </tr>
    </table>

    <div class="standard-step-score-area">
        @foreach ($standardScoreImages as $scoreImage)
            <div class="standard-step-score-block">
                @php
                    $standardScoreLabel = trim((string) ($scoreImage['match_label'] ?? '決勝スコア'));
                    if (str_contains($standardScoreLabel, 'FINAL')) {
                        $standardScoreLabel = '優勝決定戦';
                    } elseif (str_contains($standardScoreLabel, 'R1')) {
                        $standardScoreLabel = '3位決定戦';
                    }
                @endphp
                <h3>{{ $standardScoreLabel }}</h3>
                <img src="{!! $scoreImage['image'] ?? '' !!}" alt="">
            </div>
        @endforeach
    </div>
</div>
