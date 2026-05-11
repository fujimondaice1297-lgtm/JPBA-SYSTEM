@if (count($remainingScoreImages) > 0)
    <div class="official-next-score-page">
        <div class="official-next-score-main-title">
            {{ $seriesTitle }}<br>
            決勝（{{ $stageNumber($finalQualifierCount) }}名による{{ $finalFormatLabel }}）
        </div>
        @foreach ($remainingScoreImages as $index => $scoreSheetImage)
            <div class="official-next-score-block">
                <div class="official-next-score-heading">
                    <span class="official-score-logo">JPBA</span>
                    <span class="official-next-score-title">{{ $scoreHeading($scoreSheetImage, $index + 1) }}</span>
                </div>
                <img class="official-score-image" src="{!! $scoreSheetImage['image'] ?? '' !!}" alt="スコア表">
            </div>
        @endforeach
    </div>
@elseif (!isset($shootoutBracketImage) && count($scoreImages) > 0)
    <div class="official-plain-score-page">
        <h2 class="official-plain-score-title">スコア表</h2>
        @foreach ($scoreImages as $index => $scoreSheetImage)
            <div class="official-next-score-block">
                <div class="official-next-score-heading">
                    <span class="official-score-logo">JPBA</span>
                    <span class="official-score-title">{{ $scoreHeading($scoreSheetImage, $index) }}</span>
                </div>
                <img class="official-score-image" src="{!! $scoreSheetImage['image'] ?? '' !!}" alt="スコア表">
            </div>
        @endforeach
    </div>
@endif
