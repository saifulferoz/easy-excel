<?php

declare(strict_types=1);

/*
 * Writer\Pdf: the HTML renderer plus PhpSpreadsheet's Pdf page-setup surface.
 *
 * PhpSpreadsheet's Pdf writers each embed a rendering engine (Mpdf/Tcpdf/
 * Dompdf). This one stops at print-ready HTML and leaves the HTML->PDF step to
 * whatever the application already runs, so the tests pin the public API and
 * the resolution rules rather than any PDF bytes.
 */

return [
    'pdf: IOFactory builds the writer' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');

        T::ok($writer instanceof PhpOffice\PhpSpreadsheet\Writer\Pdf, 'the Pdf writer is returned');
        T::ok($writer instanceof PhpOffice\PhpSpreadsheet\Writer\Html, 'and it is an Html writer underneath');
    },

    'pdf: it renders the same HTML as the Html writer' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Trial Balance');

        $pdfHtml = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf')->generateHtmlAll();
        $htmlHtml = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Html')->generateHtmlAll();

        T::same($htmlHtml, $pdfHtml, 'the PDF path must not diverge from the HTML renderer');
        T::ok(str_contains($pdfHtml, 'Trial Balance'), 'cell content is rendered');
    },

    'pdf: the page-setup accessors round-trip' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');

        T::same(null, $writer->getPaperSize(), 'no override until one is set');
        T::same(null, $writer->getOrientation(), 'no override until one is set');
        T::same('freesans', $writer->getFont());

        $writer->setPaperSize(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $writer->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $writer->setFont('dejavusans');
        $writer->setTempDir('/tmp/pdf-writer-test');

        T::same(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4, $writer->getPaperSize());
        T::same('landscape', $writer->getOrientation());
        T::same('dejavusans', $writer->getFont());
        T::same('/tmp/pdf-writer-test', $writer->getTempDir());
    },

    'pdf: an override wins over the sheet page setup' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->getPageSetup()
            ->setPaperSize(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL)
            ->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');

        T::same('LEGAL', $writer->resolvePaperSize(), 'the sheet wins while no override is set');
        T::same('portrait', $writer->resolveOrientation());

        $writer->setPaperSize(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
        $writer->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

        T::same('A3', $writer->resolvePaperSize(), 'the override wins once set');
        T::same('landscape', $writer->resolveOrientation());
    },

    'pdf: a sheet left on the default orientation resolves to portrait' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');

        T::same('portrait', $writer->resolveOrientation(), 'every engine assumes portrait when unset');
    },

    'pdf: a paper size with no standard name resolves to its point size' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');

        $writer->setPaperSize(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_TABLOID);

        T::same([792.00, 1224.00], $writer->resolvePaperSize());
    },

    'pdf: save() writes the rendered HTML' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'saved');
        $target = \sys_get_temp_dir() . '/easy-excel-pdf-writer-' . \bin2hex(\random_bytes(4)) . '.html';

        PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf')->save($target);

        T::ok(\is_file($target), 'the file is written');
        T::ok(str_contains((string) \file_get_contents($target), 'saved'), 'with the rendered content');
        @\unlink($target);
    },
];
