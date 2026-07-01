@php
    $rawScoreImages = [];

    if (isset($scoreImages) && is_array($scoreImages)) {
        $rawScoreImages = array_values($scoreImages);
    } elseif (isset($matchScoreSheetImages) && is_array($matchScoreSheetImages)) {
        $rawScoreImages = array_values($matchScoreSheetImages);
    }

    $hasShootoutBracketImage = isset($shootoutBracketImage)
        && is_string($shootoutBracketImage)
        && trim($shootoutBracketImage) !== '';

    $scoreHeadingSafe = function ($scoreSheetImage, int $index) use ($scoreHeading) {
        if (isset($scoreHeading) && is_callable($scoreHeading)) {
            return $scoreHeading($scoreSheetImage, $index);
        }

        $label = trim((string) ($scoreSheetImage['match_label'] ?? ''));
        return $label !== '' ? $label : ($index === 0 ? '優勝決定戦' : 'スコア表');
    };

    $findChampionshipIndex = function (array $images): ?int {
        foreach ($images as $index => $image) {
            $label = trim((string) ($image['match_label'] ?? ''));
            if ($label !== '' && (str_contains($label, '優勝') || str_contains($label, '決定戦'))) {
                if (!str_contains($label, '1st') && !str_contains($label, '１st') && !str_contains($label, '2nd') && !str_contains($label, '２nd')) {
                    return (int) $index;
                }
            }
        }

        foreach ($images as $index => $image) {
            $label = trim((string) ($image['match_label'] ?? ''));
            if ($label !== '' && (str_contains($label, 'SO3') || str_contains($label, '優勝'))) {
                return (int) $index;
            }
        }

        if (count($images) > 0) {
            return count($images) - 1;
        }

        return null;
    };

    $championshipIndex = $findChampionshipIndex($rawScoreImages);
    $firstScoreImage = $championshipIndex !== null ? ($rawScoreImages[$championshipIndex] ?? null) : null;
    $remainingScoreImages = [];

    foreach ($rawScoreImages as $index => $image) {
        if ($championshipIndex !== null && (int) $index === (int) $championshipIndex) {
            continue;
        }
        $remainingScoreImages[] = $image;
    }

    $qualifierCount = 8;

    if (isset($semifinalQualifierCount) && is_numeric($semifinalQualifierCount)) {
        $qualifierCount = (int) $semifinalQualifierCount;
    } elseif (isset($shootoutPdf) && is_array($shootoutPdf)) {
        $summary = (array) ($shootoutPdf['summary'] ?? []);
        if (isset($summary['qualifier_count']) && is_numeric($summary['qualifier_count'])) {
            $qualifierCount = (int) $summary['qualifier_count'];
        }
    } elseif (isset($tournament) && isset($tournament->shootout_qualifier_count) && is_numeric($tournament->shootout_qualifier_count)) {
        $qualifierCount = (int) $tournament->shootout_qualifier_count;
    }

    $stageNumberSafe = function ($value) use ($stageNumber) {
        if (isset($stageNumber) && is_callable($stageNumber)) {
            return $stageNumber($value);
        }

        return is_numeric($value) ? number_format((int) $value) : (string) $value;
    };

    $officialMainTitleSafe = trim((string) ($officialMainTitle ?? ($tournament->name ?? '')));
    $officialSeriesTitleSafe = trim((string) ($officialSeriesTitle ?? ''));
    $officialSeasonTitleSafe = trim((string) ($officialSeasonTitle ?? ''));
    $officialVenueTitleSafe = trim((string) ($officialVenueTitle ?? ($venueText ?? '')));
    $officialSubTitle = trim($officialSeriesTitleSafe . ' ' . $officialSeasonTitleSafe);
@endphp

@if ($hasShootoutBracketImage)
    <div class="official-shootout-page">
        <div class="official-bracket-title">
            <div class="official-bracket-title-line-1 jpba-extra-heavy">{{ $officialMainTitleSafe }}</div>

            @if ($officialSubTitle !== '')
                <div class="official-bracket-title-line-2 jpba-extra-heavy">{{ $officialSubTitle }}</div>
            @endif

            <div class="official-bracket-title-line-3 jpba-extra-heavy">
                決勝（{{ $stageNumberSafe($qualifierCount) }}名によるシュートアウト方式）
            </div>

            @if ($officialVenueTitleSafe !== '')
                <div class="official-bracket-title-line-4 jpba-extra-heavy">会場：{{ $officialVenueTitleSafe }}</div>
            @endif
        </div>

        <div class="official-bracket-rule-block jpba-heavy">
            <div class="official-bracket-rule-row">①シュートアウト 1stマッチ（５位〜８位通過の４名にて１Ｇを投球し最上位者を選出）</div>
            <div class="official-bracket-rule-row">②シュートアウト 2ndマッチ（２位〜４位通過の３名及び1stマッチ最上位者の計４名にて１Ｇを投球し最上位者を選出）</div>
            <div class="official-bracket-rule-row">③優勝決定戦（トップシードと2ndマッチの最上位者にて１Ｇを投球し優勝者を決定）</div>
        </div>

        <div class="official-bracket-wrap">
            <img class="official-bracket-image" src="{!! $shootoutBracketImage !!}" alt="シュートアウト結果図">
        </div>

        @if ($firstScoreImage)
            <div class="official-score-section">
                @include('tournament_results.pdfs.partials.score_sheet_block', [
                    'scoreSheetImage' => $firstScoreImage,
                    'scoreTitle' => $scoreHeadingSafe($firstScoreImage, 0),
                    'scoreIndex' => 0,
                    'scoreBlockModifier' => 'official-score-section-block',
                    'scoreHeadingClass' => 'official-score-heading',
                    'scoreTitleClass' => 'official-score-title',
                ])
            </div>
        @endif
    </div>

    @if (count($remainingScoreImages) > 0)
        @foreach (array_chunk($remainingScoreImages, 2) as $pageIndex => $scoreSheetPage)
            <div class="official-next-score-page">
                <div class="official-next-score-main-title">
                    @if ($officialMainTitleSafe !== '')
                        {{ $officialMainTitleSafe }}<br>
                    @endif
                    @if ($officialSubTitle !== '')
                        {{ $officialSubTitle }}<br>
                    @endif
                    決勝（{{ $stageNumberSafe($qualifierCount) }}名によるシュートアウト方式）
                </div>

                @foreach ($scoreSheetPage as $index => $scoreSheetImage)
                    @php
                        $absoluteScoreIndex = ($pageIndex * 2) + $index + 1;
                    @endphp
                    @include('tournament_results.pdfs.partials.score_sheet_block', [
                        'scoreSheetImage' => $scoreSheetImage,
                        'scoreTitle' => $scoreHeadingSafe($scoreSheetImage, $absoluteScoreIndex),
                        'scoreIndex' => $absoluteScoreIndex,
                    ])
                @endforeach
            </div>
        @endforeach
    @endif
@elseif (count($rawScoreImages) > 0)
    @foreach (array_chunk($rawScoreImages, 2) as $pageIndex => $scoreSheetPage)
        <div class="official-plain-score-page">
            <div class="official-next-score-main-title">
                @if ($officialMainTitleSafe !== '')
                    {{ $officialMainTitleSafe }}<br>
                @endif
                @if ($officialSubTitle !== '')
                    {{ $officialSubTitle }}<br>
                @endif
                決勝（{{ $stageNumberSafe($qualifierCount) }}名によるシュートアウト方式）
            </div>

            @foreach ($scoreSheetPage as $index => $scoreSheetImage)
                @php
                    $absoluteScoreIndex = ($pageIndex * 2) + $index;
                @endphp
                @include('tournament_results.pdfs.partials.score_sheet_block', [
                    'scoreSheetImage' => $scoreSheetImage,
                    'scoreTitle' => $scoreHeadingSafe($scoreSheetImage, $absoluteScoreIndex),
                    'scoreIndex' => $absoluteScoreIndex,
                    'scoreTitleClass' => 'official-score-title',
                ])
            @endforeach
        </div>
    @endforeach
@endif
