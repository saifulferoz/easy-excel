<?php

declare(strict_types=1);

use EasyExcel\Compat\IOFactory;
use EasyExcel\Compat\Reader\Csv as CsvReader;
use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Compat\Writer\BaseWriter;
use EasyExcel\Compat\Writer\Csv as CsvWriter;
use EasyExcel\Compat\Writer\Html as HtmlWriter;
use EasyExcel\Compat\Writer\IWriter;
use EasyExcel\Compat\Writer\Xlsx as XlsxWriter;

return [
    'iofactory: identify and create' => function (): void {
        T::same('Xlsx', IOFactory::identify('/tmp/report.XLSX'));
        T::same('Csv', IOFactory::identify('data.csv'));
        $s = new Spreadsheet();
        T::ok(IOFactory::createWriter($s, 'Xlsx') instanceof XlsxWriter, 'xlsx writer');
        T::ok(IOFactory::createWriter($s, 'Csv') instanceof CsvWriter, 'csv writer');
        T::ok(IOFactory::createWriter($s, 'Html') instanceof HtmlWriter, 'html writer');
        T::throws(\EasyExcel\Compat\Exception::class, static fn () => IOFactory::createWriter($s, 'Ods'));
    },

    'writer: xlsx save flushes buffers and writes the file' => function (): void {
        $s = new Spreadsheet();
        $s->getActiveSheet()->setCellValue('A1', 'persisted');
        $file = \tempnam(\sys_get_temp_dir(), 'eex') . '.xlsx';
        try {
            (new XlsxWriter($s))->save($file);
            T::ok(\is_file($file) && \filesize($file) > 0, 'file written');
            T::same(1, \count(EasyExcelFake::calls('save_xlsx')));
            // the buffered A1 must have been flushed before saving
            T::ok(\str_contains((string) \file_get_contents($file), 'persisted'), 'buffer flushed before save');
        } finally {
            @\unlink($file);
        }
    },

    'writer: csv content, delimiter and guard' => function (): void {
        $s = new Spreadsheet();
        $s->getActiveSheet()->fromArray([
            ['a', 'b;c', '-danger'],
            ['x', 'say "hi"', '2'],
        ]);
        $file = \tempnam(\sys_get_temp_dir(), 'eex') . '.csv';
        try {
            (new CsvWriter($s))->setDelimiter(';')->setSanitizeFormulas(true)->save($file);
            $content = (string) \file_get_contents($file);
            T::ok(\str_contains($content, '"b;c"'), 'delimiter collision quoted');
            T::ok(\str_contains($content, "'-danger"), 'injection guard applied');
            T::ok(\str_contains($content, '"say ""hi"""'), 'quote escaping');
        } finally {
            @\unlink($file);
        }
    },

    'writer: php:// stream target works via temp file' => function (): void {
        $s = new Spreadsheet();
        $s->getActiveSheet()->setCellValue('A1', 'streamed');
        \ob_start();
        (new XlsxWriter($s))->save('php://output');
        $out = \ob_get_clean();
        T::ok(\str_contains($out, 'streamed'), 'content reached the stream');
    },

    'writer: built-ins implement IWriter / BaseWriter contract' => function (): void {
        $s = new Spreadsheet();
        $xlsx = new XlsxWriter($s);
        $csv = new CsvWriter($s);
        T::ok($xlsx instanceof IWriter && $xlsx instanceof BaseWriter, 'xlsx is an IWriter/BaseWriter');
        T::ok($csv instanceof IWriter && $csv instanceof BaseWriter, 'csv is an IWriter/BaseWriter');
        // inherited accessors round-trip and chain
        T::ok($xlsx->getPreCalculateFormulas(), 'precalc defaults true');
        T::same($xlsx, $xlsx->setIncludeCharts(true)->setPreCalculateFormulas(false), 'fluent setters return $this');
        T::ok($xlsx->getIncludeCharts() && !$xlsx->getPreCalculateFormulas(), 'flags stored');
        T::ok(!$csv->getUseDiskCaching() && $csv->getDiskCachingDirectory() === './', 'disk-cache defaults');
    },

    'writer: custom writer can extend BaseWriter and save to a resource' => function (): void {
        $s = new Spreadsheet();
        $s->getActiveSheet()->setCellValue('A1', 'custom');
        // a user-supplied writer that reuses the base file-handle plumbing
        $writer = new class($s) extends BaseWriter {
            public function __construct(private Spreadsheet $spreadsheet)
            {
            }

            public function save($filename, int $flags = 0): void
            {
                $this->processFlags($flags);
                $this->openFileHandle($filename);
                \fwrite($this->fileHandle, 'rows=' . $this->spreadsheet->getActiveSheet()->getCell('A1')->getValue());
                $this->maybeCloseFileHandle();
            }
        };
        T::ok($writer instanceof IWriter, 'anonymous writer satisfies IWriter');

        $file = \tempnam(\sys_get_temp_dir(), 'eex');
        $fh = \fopen($file, 'wb');
        try {
            $writer->save($fh); // pass an already-open resource: BaseWriter must not close it
            T::ok(\is_resource($fh), 'caller-owned handle left open');
            \fclose($fh);
            T::same('rows=custom', (string) \file_get_contents($file), 'custom writer wrote through the handle');
        } finally {
            @\unlink($file);
        }
    },

    'writer: html renders a sheet table with escaping and merges' => function (): void {
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->setTitle('Report');
        $ws->fromArray([
            ['Name', 'Note'],
            ['<b>Ann</b>', 'a & b'],
        ]);
        $ws->mergeCells('A3:B3');
        $ws->setCellValue('A3', 'footer');

        $file = \tempnam(\sys_get_temp_dir(), 'eex') . '.html';
        try {
            (new HtmlWriter($s))->save($file);
            $html = (string) \file_get_contents($file);
            T::ok(\str_contains($html, '<!DOCTYPE html>'), 'doctype emitted');
            T::ok(\str_contains($html, '<caption>Report</caption>'), 'sheet title in caption');
            T::ok(\str_contains($html, '&lt;b&gt;Ann&lt;/b&gt;'), 'cell html escaped');
            T::ok(\str_contains($html, 'a &amp; b'), 'ampersand escaped');
            T::ok(\str_contains($html, 'colspan="2"'), 'merged range becomes colspan');
        } finally {
            @\unlink($file);
        }
    },

    'writer: html generate pieces and sheet navigation' => function (): void {
        $s = new Spreadsheet();
        $s->getActiveSheet()->setTitle('One');
        $s->getActiveSheet()->setCellValue('A1', 'x');
        $s->createSheet()->setTitle('Two');

        $w = new HtmlWriter($s);
        T::same(0, $w->getSheetIndex(), 'defaults to first sheet');
        // single-sheet mode: no navigation block
        T::ok(!\str_contains($w->generateHtmlAll(), '<nav'), 'no nav for single sheet');

        T::same($w, $w->writeAllSheets(), 'writeAllSheets is fluent');
        T::same(null, $w->getSheetIndex(), 'writeAllSheets clears the index');
        $all = $w->generateHtmlAll();
        T::ok(\str_contains($all, '<nav class="sheet-navigation">'), 'nav block for all sheets');
        T::ok(\str_contains($all, '#sheet0') && \str_contains($all, '#sheet1'), 'nav links both sheets');
        T::ok(\str_contains($w->generateStyles(false), '<style'), 'styles fragment');
        T::ok(\str_contains($w->generateHTMLFooter(), '</html>'), 'footer closes document');
    },

    'reader: csv loads in chunks with binding' => function (): void {
        $file = \tempnam(\sys_get_temp_dir(), 'eex') . '.csv';
        \file_put_contents($file, "name,qty\nwidget,3\ngadget,0150\n");
        try {
            $s = (new CsvReader())->load($file);
            $ws = $s->getActiveSheet();
            T::same('name', $ws->getCell('A1')->getValue());
            T::same(3.0, $ws->getCell('B2')->getValue(), 'numeric string bound to number');
            T::same('0150', $ws->getCell('B3')->getValue(), 'leading zero preserved');
        } finally {
            @\unlink($file);
        }
    },

    'bootstrap: PhpOffice\\PhpSpreadsheet aliases resolve' => function (): void {
        T::ok(\class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet'), 'root class aliased');
        $s = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        T::ok($s instanceof Spreadsheet, 'alias is the compat class');
        $s->getActiveSheet()->setCellValue('A1', 'via alias');
        T::same('via alias', $s->getActiveSheet()->getCell('A1')->getValue());
        T::ok(\class_exists('PhpOffice\\PhpSpreadsheet\\Cell\\Coordinate'), 'nested namespace aliased');
        T::same(27, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('AA'));
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($s, 'Xlsx');
        T::ok($writer instanceof XlsxWriter, 'IOFactory through alias');
    },
];
