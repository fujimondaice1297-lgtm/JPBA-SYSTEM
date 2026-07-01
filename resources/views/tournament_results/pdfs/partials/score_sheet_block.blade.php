@php
    $scoreTitleText = trim((string) ($scoreTitle ?? ''));
    if ($scoreTitleText === '' && isset($scoreHeading) && is_callable($scoreHeading)) {
        $scoreTitleText = $scoreHeading($scoreSheetImage ?? [], (int) ($scoreIndex ?? 0));
    }
    if ($scoreTitleText === '') {
        $scoreTitleText = 'スコア表';
    }

    $scoreBlockClass = trim('official-next-score-block ' . (string) ($scoreBlockModifier ?? ''));
    $scoreHeadingClass = trim((string) ($scoreHeadingClass ?? 'official-next-score-heading'));
    $scoreTitleClass = trim((string) ($scoreTitleClass ?? 'official-next-score-title'));
    $scoreVenueText = trim((string) ($venueText ?? $officialVenueTitle ?? ''));
    $scoreDateText = trim((string) ($dateText ?? ''));
    $scoreLaneText = trim((string) ($scoreSheetImage['lane_label'] ?? ''));
    $scoreGameNumber = (int) ($scoreSheetImage['game_number'] ?? 0);
    $scoreMetaItems = [];

    if ($scoreVenueText !== '') {
        $scoreMetaItems[] = '会場：' . $scoreVenueText;
    }
    if ($scoreDateText !== '') {
        $scoreMetaItems[] = '開催日：' . $scoreDateText;
    }
    if ($scoreLaneText !== '') {
        $scoreMetaItems[] = 'レーン：' . $scoreLaneText;
    }
    if ($scoreGameNumber > 0) {
        $scoreMetaItems[] = $scoreGameNumber . 'G';
    }
@endphp

<div class="{{ $scoreBlockClass }}">
    <div class="{{ $scoreHeadingClass }}">
        @if (!empty($jpbaLogoSrc))
            <img class="official-score-logo-image" src="{!! $jpbaLogoSrc !!}" alt="JPBA">
        @else
            <span class="official-score-logo">JPBA</span>
        @endif
        <span class="{{ $scoreTitleClass }}">{{ $scoreTitleText }}</span>
    </div>

    @if (count($scoreMetaItems) > 0)
        <div class="official-score-meta jpba-heavy">{{ implode(' ／ ', $scoreMetaItems) }}</div>
    @endif

    <div class="official-score-image-frame">
        <img class="official-score-image" src="{!! $scoreSheetImage['image'] ?? '' !!}" alt="スコア表">
    </div>
</div>
