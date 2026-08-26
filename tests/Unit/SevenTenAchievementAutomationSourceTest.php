<?php

test('confirmed match score sheets trigger seven ten candidate detection', function () {
    $basePath = dirname(__DIR__, 2);
    $controller = file_get_contents(
        $basePath.'/app/Http/Controllers/TournamentMatchScoreSheetController.php',
    );
    $command = file_get_contents(
        $basePath.'/app/Console/Commands/DetectAchievementCandidatesCommand.php',
    );

    expect($controller)
        ->toContain('AchievementDetectionService::class')
        ->toContain('scanTournament((int) $tournament->id)');
    expect($command)
        ->toContain('TournamentMatchScoreSheet::query()')
        ->toContain('seven_ten_candidates_created');
});

test('seven ten evidence remains linked to its source frame', function () {
    $basePath = dirname(__DIR__, 2);
    $migration = file_get_contents(
        $basePath.'/database/migrations/2026_08_27_000001_link_record_types_to_match_score_frames.php',
    );
    $model = file_get_contents($basePath.'/app/Models/RecordType.php');

    expect($migration)
        ->toContain("foreignId('source_match_score_frame_id')")
        ->toContain("constrained('tournament_match_score_frames')")
        ->toContain('nullOnDelete()');
    expect($model)
        ->toContain("'source_match_score_frame_id'")
        ->toContain('sourceMatchScoreFrame');
});

test('score sheet explains the exact manual confirmation boundary', function () {
    $basePath = dirname(__DIR__, 2);
    $view = file_get_contents(
        $basePath.'/resources/views/tournament_match_score_sheets/index.blade.php',
    );

    expect($view)
        ->toContain('7番・10番だけ')
        ->toContain('公認7－10メイドの確認待ち候補')
        ->toContain('公認番号と件数はスタッフが確認した時点で反映します');
});
