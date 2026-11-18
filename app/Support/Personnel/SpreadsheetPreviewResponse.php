<?php

declare(strict_types=1);

namespace App\Support\Personnel;

use App\Services\Personnel\RenderSpreadsheetPdfPreviewService;
use App\Support\Files\StoredFileResponder;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SpreadsheetPreviewResponse
{
    public static function make(string $diskPath, string $title): BinaryFileResponse|View
    {
        $absolute = StoredFileResponder::absolute($diskPath);
        $renderer = app(RenderSpreadsheetPdfPreviewService::class);
        $pdf = $renderer->pdfAbsolute($absolute);
        $relative = is_string($pdf) ? $renderer->relativeDiskPath($pdf) : null;

        if (is_string($pdf) && is_string($relative) && is_file($pdf)) {
            $name = pathinfo($title, PATHINFO_FILENAME);
            if ($name === '') {
                $name = 'planilla';
            }

            return StoredFileResponder::stream($relative, $name.'.pdf', 'application/pdf', true);
        }

        return view('modules.personnel-documents.xlsx-preview', [
            'title' => $title,
            'table' => XlsxPreviewHtml::fromPath($absolute),
            'fallback' => true,
        ]);
    }
}
