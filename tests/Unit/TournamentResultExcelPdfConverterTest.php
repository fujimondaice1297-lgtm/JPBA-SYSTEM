<?php

test('windows excel pdf conversion bypasses powershell and windows script host', function () {
    $basePath = dirname(__DIR__, 2);
    $converter = file_get_contents(
        $basePath.'/app/Services/TournamentResultExcelPdfConverter.php'
    );
    $source = file_get_contents(
        $basePath.'/tools/ExcelPdfConverter.cs'
    );
    $binaryPath = $basePath.'/tools/convert_excel_to_pdf.exe';

    expect($converter)
        ->toContain("base_path('tools/convert_excel_to_pdf.exe')")
        ->toContain('@set_time_limit(300)')
        ->toContain("'jpba_excel_pdf.lock'")
        ->toContain('flock($lockHandle, LOCK_EX)')
        ->not->toContain("'powershell.exe'")
        ->not->toContain("'cscript.exe'")
        ->not->toContain('convert_excel_to_pdf.vbs');

    expect($source)
        ->toContain('Type.GetTypeFromProgID("Excel.Application")')
        ->toContain('workbookCollection.Open(inputPath, 0, true)')
        ->toContain('ExportAsFixedFormat')
        ->toContain('Marshal.FinalReleaseComObject')
        ->toContain('((dynamic)excel).Quit()');

    expect(is_file($binaryPath))->toBeTrue();
    expect(file_get_contents($binaryPath, false, null, 0, 2))->toBe('MZ');
});
