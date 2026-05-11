@include('tournament_results.pdfs.partials.result_page')

@if (($finalFormat ?? view()->shared('finalFormat', 'standard')) === 'shootout')
    @include('tournament_results.pdfs.partials.shootout_pages')
@elseif (($finalFormat ?? view()->shared('finalFormat', 'standard')) === 'single_elimination')
    @includeIf('tournament_results.pdfs.partials.single_elimination_pages')
@endif

@include('tournament_results.pdfs.partials.snapshots')
