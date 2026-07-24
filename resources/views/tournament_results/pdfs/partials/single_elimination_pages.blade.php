@php
    $hasSingleEliminationBracketImage = isset($singleEliminationBracketImage)
        && is_string($singleEliminationBracketImage)
        && trim($singleEliminationBracketImage) !== '';

    $singleEliminationPdfData = isset($singleEliminationPdf) && is_array($singleEliminationPdf)
        ? $singleEliminationPdf
        : [];

    $singleSummary = (array) ($singleEliminationPdfData['summary'] ?? []);
    $singleMeta = (array) ($singleEliminationPdfData['meta'] ?? []);

    $singleQualifierCount = (int) ($singleSummary['qualifier_count'] ?? $finalQualifierCount ?? 0);
    $singleBracketSize = (int) ($singleSummary['bracket_size'] ?? 0);
    $singleCompletedCount = (int) ($singleSummary['completed_match_count'] ?? 0);
    $singleActualMatchCount = (int) ($singleSummary['actual_match_count'] ?? 0);
    $singleWinnerName = trim((string) ($singleSummary['winner_name'] ?? ''));
    $singleSeedSourceName = trim((string) ($singleMeta['seed_source_name'] ?? ''));

    $stageNumberSafe = function ($value) use ($stageNumber) {
        if (isset($stageNumber) && is_callable($stageNumber)) {
            return $stageNumber($value);
        }

        return is_numeric($value) ? number_format((int) $value) : '-';
    };

    $officialMainTitleSafe = trim((string) ($officialMainTitle ?? ($tournament->name ?? '')));
    $officialSeriesTitleSafe = trim((string) ($officialSeriesTitle ?? ''));
    $officialSeasonTitleSafe = trim((string) ($officialSeasonTitle ?? ''));
    $officialVenueTitleSafe = trim((string) ($officialVenueTitle ?? ($venueText ?? '')));
@endphp

@if ($hasSingleEliminationBracketImage)
    <div class="official-single-elimination-page" @if (!empty($suppressInitialDiagramPageBreak)) style="page-break-before: auto;" @endif>
        <div class="official-single-elimination-title">
            <div class="official-single-elimination-title-line-1 jpba-extra-heavy">{{ $officialMainTitleSafe }}</div>

            @if ($officialSeriesTitleSafe !== '' || $officialSeasonTitleSafe !== '')
                <div class="official-single-elimination-title-line-2 jpba-extra-heavy">
                    {{ trim($officialSeriesTitleSafe . ' ' . $officialSeasonTitleSafe) }}
                </div>
            @endif

            <div class="official-single-elimination-title-line-3 jpba-extra-heavy">
                決勝（{{ $stageNumberSafe($singleQualifierCount) }}名によるトーナメント方式）
            </div>

            @if ($officialVenueTitleSafe !== '')
                <div class="official-single-elimination-title-line-4 jpba-heavy">
                    会場：{{ $officialVenueTitleSafe }}
                </div>
            @endif
        </div>

        <div class="official-single-elimination-meta jpba-heavy">
            <span>進出元：{{ $singleSeedSourceName !== '' ? $singleSeedSourceName : '進出成績' }}</span>
            @if ($singleBracketSize > 0)
                <span>／ ブラケット：{{ number_format($singleBracketSize) }}枠</span>
            @endif
            @if ($singleActualMatchCount > 0)
                <span>／ 試合進行：{{ number_format($singleCompletedCount) }} / {{ number_format($singleActualMatchCount) }}</span>
            @endif
            @if ($singleWinnerName !== '')
                <span>／ 優勝：{{ $singleWinnerName }}</span>
            @endif
        </div>

        <div class="official-single-elimination-wrap">
            <img class="official-single-elimination-image" src="{!! $singleEliminationBracketImage !!}" alt="決勝トーナメント表">
        </div>
    </div>
@endif
