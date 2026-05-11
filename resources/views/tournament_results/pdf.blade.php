<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>
        @php
            $jp = fn (string $s) => html_entity_decode($s, ENT_QUOTES, 'UTF-8');
            $titlePdf = $jp('&#x6700;&#x7D42;&#x6210;&#x7E3E;PDF');
        @endphp
        {{ isset($tournament) ? $tournament->year . $jp('&#x5E74;') . ' ' . $tournament->name . ' ' . $titlePdf : $titlePdf }}
    </title>
    @include('tournament_results.pdfs.partials.styles')
</head>
<body>
    @include('tournament_results.pdfs.partials.context')

    @php
        // context.blade.php で確定した外枠モードを読む。
        // シーズントライアルかどうかと、決勝方式（shootout / single_elimination）は別軸。
        $resolvedPdfMode = view()->shared('pdfMode', 'standard');
    @endphp

    @if ($resolvedPdfMode === 'season_trial')
        @include('tournament_results.pdfs.season_trial')
    @elseif ($resolvedPdfMode === 'single_elimination')
        @include('tournament_results.pdfs.single_elimination')
    @elseif ($resolvedPdfMode === 'shootout')
        @include('tournament_results.pdfs.shootout')
    @else
        @include('tournament_results.pdfs.standard')
    @endif
</body>
</html>
