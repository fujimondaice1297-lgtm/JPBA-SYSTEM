<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

test('rokko queens format is routed only when its selected version is assigned', function () {
    $basePath = dirname(__DIR__, 2);
    $controller = file_get_contents(
        $basePath.'/app/Http/Controllers/TournamentResultController.php'
    );

    expect($controller)
        ->toContain("\$tournament->resultFormatVersion?->format?->code === 'rokko_queens'")
        ->toContain('RokkoQueensResultExportService')
        ->toContain('makeOfficialStandardTournamentPdf')
        ->toContain('makePdfWithJapaneseFont');
});

test('rokko queens workbook has official page orientations and stable anchors', function () {
    $basePath = dirname(__DIR__, 2);
    $path = $basePath.'/resources/tournament_result_formats/rokko_queens_v1.xlsx';
    $book = IOFactory::load($path);

    expect(array_map(
        fn ($sheet) => $sheet->getTitle(),
        $book->getAllSheets()
    ))->toBe([
        '1_大会概要',
        '2_最終成績',
        '3_TV決勝',
        '年度設定',
    ]);

    foreach ([
        'RQ1_TITLE',
        'RQ2_RESULTS_ANCHOR',
        'RQ2_WINNER_PHOTO_ANCHOR',
        'RQ3_SEED1',
        'RQ3_SEED4',
        'RQ3_FINAL_IMAGE_ANCHOR',
        'RQ3_R2_IMAGE_ANCHOR',
        'RQ3_R1_IMAGE_ANCHOR',
        'RQ_INPUT_OVERVIEW_TITLE',
        'RQ_INPUT_RESULT_TITLE',
        'RQ_INPUT_HOST',
        'RQ_INPUT_VENUE',
        'RQ_INPUT_BROADCAST',
    ] as $name) {
        expect($book->getDefinedName($name))->not->toBeNull();
    }

    expect($book->getSheetByName('年度設定')->getSheetState())->toBe('visible');
    expect($book->getActiveSheet()->getTitle())->toBe('年度設定');
    foreach (['1_大会概要', '2_最終成績'] as $sheetName) {
        expect($book->getSheetByName($sheetName)->getPageSetup()->getOrientation())
            ->toBe('landscape');
    }
    expect($book->getSheetByName('3_TV決勝')->getPageSetup()->getOrientation())
        ->toBe('portrait');
});

test('rokko winner image source is the player profile and official pdf image is not imported', function () {
    $basePath = dirname(__DIR__, 2);
    $service = file_get_contents(
        $basePath.'/app/Services/RokkoQueensResultExportService.php'
    );

    expect($service)
        ->toContain('$winner->public_image_path')
        ->toContain('$winner->image_path')
        ->toContain('matchScoreSheetImages')
        ->toContain("getSheetByName('年度設定')")
        ->toContain('templateOverride')
        ->not->toContain('FInalResult.pdf')
        ->not->toContain('FinalResult.pdf');
});

test('rokko migration registers one dedicated version without changing legacy diagram services', function () {
    $basePath = dirname(__DIR__, 2);
    $migration = file_get_contents(
        $basePath.'/database/migrations/2026_07_26_000001_add_rokko_queens_result_format.php'
    );
    $stepLadder = file_get_contents(
        $basePath.'/app/Services/StepLadderBracketImageService.php'
    );

    expect($migration)
        ->toContain("'code' => 'rokko_queens'")
        ->toContain("'template_path' => 'resources/tournament_result_formats/rokko_queens_v1.xlsx'")
        ->toContain("'tournament_result_format_version_id' => \$versionId");
    expect($stepLadder)->toContain('class StepLadderBracketImageService');
});
