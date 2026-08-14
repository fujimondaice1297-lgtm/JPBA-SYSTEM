<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class TournamentResultExcelPdfConverter
{
    public function convert(string $xlsxPath, string $pdfPath): string
    {
        if (! is_file($xlsxPath)) {
            throw new RuntimeException('PDF変換元のExcelが見つかりません。');
        }

        $directory = dirname($pdfPath);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('PDF一時保存先を作成できません。');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->convertWithMicrosoftExcel($xlsxPath, $pdfPath);
        }

        return $this->convertWithLibreOffice($xlsxPath, $pdfPath);
    }

    private function convertWithMicrosoftExcel(string $xlsxPath, string $pdfPath): string
    {
        // Excelの初回起動やPDF書き出しは30秒を超える場合があるため、
        // Webリクエストの既定上限より長い変換時間を確保する。
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $converter = base_path('tools/convert_excel_to_pdf.exe');
        if (! is_file($converter)) {
            throw new RuntimeException('Excel PDF変換プログラムが見つかりません。');
        }

        $safeBase = 'jpba_result_'.bin2hex(random_bytes(8));
        $inputCopy = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$safeBase.'.xlsx';
        $outputCopy = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$safeBase.'.pdf';
        $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'jpba_excel_pdf.lock';
        $lockHandle = null;

        if (! copy($xlsxPath, $inputCopy)) {
            throw new RuntimeException('Excelの変換用コピーを作成できません。');
        }

        try {
            $lockHandle = fopen($lockPath, 'c+');
            if ($lockHandle === false || ! flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Excel PDF変換の排他ロックを取得できませんでした。');
            }

            $lastError = null;
            foreach (range(1, 2) as $attempt) {
                @unlink($outputCopy);

                try {
                    $process = new Process([
                        $converter,
                        $inputCopy,
                        $outputCopy,
                    ], base_path());
                    $process->setTimeout(240);
                    $process->mustRun();
                    $lastError = null;
                    break;
                } catch (\Throwable $e) {
                    $lastError = $e;
                    if ($attempt === 1) {
                        usleep(500000);
                    }
                }
            }

            if ($lastError !== null) {
                throw $lastError;
            }

            if (! is_file($outputCopy) || ! copy($outputCopy, $pdfPath)) {
                throw new RuntimeException('Microsoft ExcelはPDFを生成できませんでした。');
            }
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Microsoft ExcelによるPDF化に失敗しました。'.$e->getMessage(),
                previous: $e
            );
        } finally {
            if (is_resource($lockHandle)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
            @unlink($inputCopy);
            @unlink($outputCopy);
        }

        return $pdfPath;
    }

    private function convertWithLibreOffice(string $xlsxPath, string $pdfPath): string
    {
        $binary = env('LIBREOFFICE_BINARY', 'libreoffice');
        $outputDirectory = dirname($pdfPath);
        $process = new Process([
            $binary,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDirectory,
            $xlsxPath,
        ]);
        $process->setTimeout(180);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'LibreOfficeによるPDF化に失敗しました。LIBREOFFICE_BINARYを確認してください。',
                previous: $e
            );
        }

        $generated = $outputDirectory.DIRECTORY_SEPARATOR.pathinfo($xlsxPath, PATHINFO_FILENAME).'.pdf';
        if (! is_file($generated)) {
            throw new RuntimeException('LibreOfficeはPDFを生成できませんでした。');
        }

        if ($generated !== $pdfPath && ! rename($generated, $pdfPath)) {
            throw new RuntimeException('生成したPDFを所定の場所へ移動できません。');
        }

        return $pdfPath;
    }
}
