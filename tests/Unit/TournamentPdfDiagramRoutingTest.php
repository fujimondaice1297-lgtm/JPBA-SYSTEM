<?php

test('standard tournament pdf uses the existing image based diagrams', function () {
    $basePath = dirname(__DIR__, 2);
    $template = file_get_contents(
        $basePath.'/resources/views/tournament_results/pdfs/standard.blade.php'
    );

    expect($template)
        ->toContain("partials.step_ladder_pages")
        ->toContain("partials.single_elimination_pages")
        ->toContain("partials.score_sheets")
        ->toContain("suppressInitialDiagramPageBreak")
        ->toContain("suppressInitialScorePageBreak")
        ->not->toContain("partials.standard_step_ladder_page");
});

test('mixed orientation pdf only enables diagram pages when image services returned data', function () {
    $basePath = dirname(__DIR__, 2);
    $controller = file_get_contents(
        $basePath.'/app/Http/Controllers/TournamentResultController.php'
    );

    expect($controller)
        ->toContain("\$data['stepLadderBracketImage']")
        ->toContain("\$data['singleEliminationBracketImage']");
});
