@php
    $resolvedOfficialTitleClass = (string) ($officialTitleClass ?? view()->shared('officialTitleClass', ''));
@endphp

<div class="official-top-title">
    <div class="official-logo-wrap">
        @if ($jpbaLogoSrc)
            <img class="official-logo-image" src="{!! $jpbaLogoSrc !!}" alt="JPBA">
        @else
            <div class="official-logo-text">JPBA</div>
        @endif
    </div>

    <table class="official-title-table">
        <tr>
            <td class="official-title-line-1 {{ $resolvedOfficialTitleClass }} jpba-extra-heavy">{{ $officialMainTitle }}</td>
        </tr>
    </table>
    @if (($isSeasonTrialPdf ?? false) && $officialSeriesTitle !== '')
        <div class="official-title-line-2 jpba-extra-heavy">{{ $officialSeriesTitle }}</div>
    @endif
    @if (($isSeasonTrialPdf ?? false) && $officialSeasonTitle !== '')
        <div class="official-title-line-3 jpba-extra-heavy">{{ $officialSeasonTitle }}</div>
    @endif
    <div class="official-title-line-4 jpba-extra-heavy">会場：{{ $officialVenueTitle }}</div>
    <div class="official-title-line-5 jpba-extra-heavy">成績表</div>
</div>

<table class="official-info jpba-heavy">
    <tr>
        <th>【主　　催】</th>
        <td class="left-info">（公社）日本プロボウリング協会</td>
        <th>【開催日】</th>
        <td class="right-info">{{ $dateText !== '' ? $dateText : '-' }}</td>
    </tr>
    <tr>
        <th>【公　　認】</th>
        <td class="left-info">（公社）日本プロボウリング協会</td>
        <th>【会　　場】</th>
        <td class="right-info">{{ $venueText !== '' ? $venueText : '-' }}</td>
    </tr>
    <tr>
        <th>【主管運営】</th>
        <td class="left-info">会場該当地区 及び事務局</td>
        <th>【競技内容】</th>
        <td class="right-info">決勝･･･{{ $stageNumber($finalQualifierCount) }}名による{{ $finalFormatLabel }}</td>
    </tr>
</table>

<div class="official-competition-note jpba-heavy">
    <div class="official-competition-note-row">
        <span class="official-competition-label">【競技内容】</span>
        予　選･･･{{ $stageNumber($prelimPlayerCount) }}名にて{{ $stageNumber($prelimGameCount) }}Ｇ投球し上位{{ $stageNumber($prelimQualifierCount) }}名を次ステージへ選出。
    </div>
    <div class="official-competition-note-row">
        <span class="official-competition-label"></span>
        準決勝･･･{{ $stageNumber($prelimQualifierCount) }}名にて{{ $stageNumber($semifinalGameCount) }}Ｇ投球し通算{{ $stageNumber($semifinalTotalGameCount) }}Ｇ上位{{ $stageNumber($finalQualifierCount) }}名を決勝へ選出。
    </div>
    <div class="official-competition-note-row">
        <span class="official-competition-label"></span>
        決　勝･･･{{ $stageNumber($finalQualifierCount) }}名による{{ $finalFormatLabel }}。
    </div>
</div>
