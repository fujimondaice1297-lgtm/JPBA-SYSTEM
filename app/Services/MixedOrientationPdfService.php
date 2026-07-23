<?php

namespace App\Services;

use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class MixedOrientationPdfService
{
    /**
     * @param array<int,string> $pdfBinaries
     */
    public function merge(array $pdfBinaries): string
    {
        $merged = new Fpdi();
        $pageCount = 0;

        foreach ($pdfBinaries as $binary) {
            if (! is_string($binary) || $binary === '') {
                continue;
            }

            $sourcePages = $merged->setSourceFile(StreamReader::createByString($binary));

            for ($page = 1; $page <= $sourcePages; $page++) {
                $templateId = $merged->importPage($page);
                $size = $merged->getTemplateSize($templateId);

                if (! is_array($size) || empty($size['width']) || empty($size['height'])) {
                    throw new RuntimeException('結合対象PDFのページサイズを取得できませんでした。');
                }

                $width = (float) $size['width'];
                $height = (float) $size['height'];
                $orientation = $width > $height ? 'L' : 'P';

                $merged->AddPage($orientation, [$width, $height]);
                $merged->useTemplate($templateId, 0, 0, $width, $height, true);
                $pageCount++;
            }
        }

        if ($pageCount === 0) {
            throw new RuntimeException('結合できるPDFページがありません。');
        }

        return $merged->Output('S');
    }
}
