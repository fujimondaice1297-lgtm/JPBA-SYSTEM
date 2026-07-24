@php
    $allScoreImages = isset($scoreImages) && is_array($scoreImages) ? array_values($scoreImages) : [];
    $scoreSheetPages = array_chunk($allScoreImages, 2);
    $scorePageMainTitle = trim((string) ($seriesTitle ?? $officialMainTitle ?? '大会成績'));
    $scorePageFinalLine = '決勝（' . $stageNumber($finalQualifierCount) . '名による' . $finalFormatLabel . '）';
@endphp

@if (!isset($shootoutBracketImage) && count($allScoreImages) > 0)
    @foreach ($scoreSheetPages as $pageIndex => $scoreSheetPage)
        <div class="official-plain-score-page" @if (!empty($suppressInitialScorePageBreak) && $pageIndex === 0) style="page-break-before: auto;" @endif>
            <div class="official-next-score-main-title">
                @if ($scorePageMainTitle !== '')
                    {{ $scorePageMainTitle }}<br>
                @endif
                {{ $scorePageFinalLine }}
            </div>

            @foreach ($scoreSheetPage as $index => $scoreSheetImage)
                @php
                    $absoluteScoreIndex = ($pageIndex * 2) + $index;
                @endphp
                @include('tournament_results.pdfs.partials.score_sheet_block', [
                    'scoreSheetImage' => $scoreSheetImage,
                    'scoreTitle' => $scoreHeading($scoreSheetImage, $absoluteScoreIndex),
                    'scoreIndex' => $absoluteScoreIndex,
                    'scoreTitleClass' => 'official-score-title',
                ])
            @endforeach
        </div>
    @endforeach
@endif
