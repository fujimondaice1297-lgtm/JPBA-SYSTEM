@php
    $standardSection = trim((string) ($standardPdfSection ?? 'all'));
@endphp

@if ($standardSection !== 'all')
    <style>
        .official-round-robin-page,
        .official-snapshot-page {
            page-break-before: auto !important;
        }
    </style>
@endif

@if ($standardSection === 'overview')
    @include('tournament_results.pdfs.partials.standard_overview')
@elseif ($standardSection === 'awards')
    @include('tournament_results.pdfs.partials.standard_awards_page')
@elseif ($standardSection === 'step_ladder')
    @include('tournament_results.pdfs.partials.standard_step_ladder_page')
@elseif ($standardSection === 'round_robin_ranking')
    @include('tournament_results.pdfs.partials.round_robin_pages', ['roundRobinPageMode' => 'ranking'])
@elseif ($standardSection === 'round_robin_matches')
    @include('tournament_results.pdfs.partials.round_robin_pages', ['roundRobinPageMode' => 'matches'])
@elseif ($standardSection === 'semifinal')
    @include('tournament_results.pdfs.partials.snapshots', [
        'standardSnapshotResultCode' => 'semifinal_total',
        'standardSnapshotChunkIndex' => 0,
    ])
@elseif ($standardSection === 'prelim_1')
    @include('tournament_results.pdfs.partials.snapshots', [
        'standardSnapshotResultCode' => 'prelim_total',
        'standardSnapshotChunkIndex' => 0,
    ])
@elseif ($standardSection === 'prelim_2')
    @include('tournament_results.pdfs.partials.snapshots', [
        'standardSnapshotResultCode' => 'prelim_total',
        'standardSnapshotChunkIndex' => 1,
    ])
@else
    @include('tournament_results.pdfs.partials.standard_overview')
    @include('tournament_results.pdfs.partials.standard_awards_page')
    @include('tournament_results.pdfs.partials.standard_step_ladder_page')
    @include('tournament_results.pdfs.partials.round_robin_pages')
    @include('tournament_results.pdfs.partials.single_elimination_match_summary')
    @include('tournament_results.pdfs.partials.selection_scores')
    @include('tournament_results.pdfs.partials.snapshots')
@endif
