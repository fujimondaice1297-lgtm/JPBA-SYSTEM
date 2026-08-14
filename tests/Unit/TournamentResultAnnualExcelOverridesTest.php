<?php

use App\Services\PureFoodsKishiResultExportService;
use App\Services\RokkoQueensResultExportService;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

test('annual excel values override tournament defaults while blank cells keep defaults', function (string $serviceClass) {
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('年度設定');
    $book->addNamedRange(new NamedRange('ANNUAL_INPUT', $sheet, 'B8'));

    $service = (new ReflectionClass($serviceClass))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($serviceClass, 'templateOverride');

    $sheet->setCellValue('B8', '2027年度の表示');
    expect($method->invoke($service, $book, 'ANNUAL_INPUT', '大会作成画面の値'))
        ->toBe('2027年度の表示');

    $sheet->setCellValue('B8', '');
    expect($method->invoke($service, $book, 'ANNUAL_INPUT', '大会作成画面の値'))
        ->toBe('大会作成画面の値');

    expect($method->invoke($service, $book, 'MISSING_INPUT', '大会作成画面の値'))
        ->toBe('大会作成画面の値');
})->with([
    PureFoodsKishiResultExportService::class,
    RokkoQueensResultExportService::class,
]);
