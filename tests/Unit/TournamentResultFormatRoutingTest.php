<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

test('purefoods format is routed only when its selected version is assigned', function () {
    $basePath = dirname(__DIR__, 2);
    $controller = file_get_contents(
        $basePath.'/app/Http/Controllers/TournamentResultController.php'
    );

    expect($controller)
        ->toContain("\$tournament->resultFormatVersion?->format?->code === 'purefoods_kishi'")
        ->toContain('PureFoodsKishiResultExportService')
        ->toContain('makeOfficialStandardTournamentPdf')
        ->toContain('makePdfWithJapaneseFont');
});

test('purefoods workbook has four printable pages and stable named anchors', function () {
    $basePath = dirname(__DIR__, 2);
    $path = $basePath.'/resources/tournament_result_formats/purefoods_kishi_v1.xlsx';
    $book = IOFactory::load($path);

    expect(array_map(
        fn ($sheet) => $sheet->getTitle(),
        $book->getAllSheets()
    ))->toBe([
        '1_大会概要',
        '2_最終成績',
        '3_決勝スコア',
        '4_対戦表',
        '年度設定',
    ]);

    foreach ([
        'PF_TITLE',
        'PF2_RESULTS_ANCHOR',
        'PF2_WINNER_PHOTO_ANCHOR',
        'PF3_FINAL_IMAGE_ANCHOR',
        'PF3_SF2_IMAGE_ANCHOR',
        'PF3_SF1_IMAGE_ANCHOR',
        'PF4_BRACKET_IMAGE_ANCHOR',
        'PF_INPUT_OVERVIEW_TITLE',
        'PF_INPUT_RESULT_TITLE',
        'PF_INPUT_HOST',
        'PF_INPUT_SPONSOR',
        'PF_INPUT_VENUE',
        'PF_INPUT_BROADCAST',
    ] as $name) {
        expect($book->getDefinedName($name))->not->toBeNull();
    }

    expect($book->getSheetByName('年度設定')->getSheetState())->toBe('visible');
    expect($book->getActiveSheet()->getTitle())->toBe('年度設定');
    expect($book->getSheetByName('1_大会概要')->getPageSetup()->getOrientation())->toBe('landscape');
    expect($book->getSheetByName('2_最終成績')->getPageSetup()->getOrientation())->toBe('landscape');
    expect($book->getSheetByName('3_決勝スコア')->getPageSetup()->getOrientation())->toBe('portrait');
    expect($book->getSheetByName('4_対戦表')->getPageSetup()->getOrientation())->toBe('landscape');
});

test('winner image source is the player profile and official pdf image is not imported', function () {
    $basePath = dirname(__DIR__, 2);
    $service = file_get_contents(
        $basePath.'/app/Services/PureFoodsKishiResultExportService.php'
    );

    expect($service)
        ->toContain('$winner->public_image_path')
        ->toContain('$winner->image_path')
        ->toContain("getSheetByName('年度設定')")
        ->toContain('templateOverride')
        ->not->toContain('FinalResult.pdf');
});
