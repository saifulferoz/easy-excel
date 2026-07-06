<?php

declare(strict_types=1);

use EasyExcel\Compat\IOFactory;
use EasyExcel\Compat\Reader\Csv as CsvReader;
use EasyExcel\Compat\Reader\Xlsx as XlsxReader;
use EasyExcel\Compat\Shared\StreamPath;
use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Compat\Writer\BaseWriter;
use EasyExcel\Compat\Writer\Csv as CsvWriter;
use EasyExcel\Compat\Writer\Html as HtmlWriter;
use EasyExcel\Compat\Writer\IWriter;
use EasyExcel\Compat\Writer\Xlsx as XlsxWriter;

/** In-memory stream wrapper standing in for gaufrette:// / s3:// style userland wrappers. */
final class EexTestStreamWrapper
{
    /** @var array<string, string> */
    public static array $files = [];

    /** @var resource|null */
    public $context;

    private string $path;
    private int $pos = 0;

    public function stream_open(string $path, string $mode): bool
    {
        $this->path = $path;
        if (\str_contains($mode, 'r')) {
            return isset(self::$files[$path]);
        }
        self::$files[$path] = '';

        return true;
    }

    public function stream_write(string $data): int
    {
        self::$files[$this->path] .= $data;

        return \strlen($data);
    }

    public function stream_read(int $count): string
    {
        $chunk = \substr(self::$files[$this->path], $this->pos, $count);
        $this->pos += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->pos >= \strlen(self::$files[$this->path]);
    }

    public function stream_tell(): int
    {
        return $this->pos;
    }

    /** @return array<int|string, int> */
    public function stream_stat(): array
    {
        return [];
    }

    public function stream_close(): void
    {
    }
}

/** Custom writer for the registerWriter tests (the app-side 'Pdf' writer pattern). */
final class FakePdfWriter extends BaseWriter
{
    public Spreadsheet $book;

    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->book = $spreadsheet;
    }

    public function save($filename, int $flags = 0): void
    {
        if (\is_string($filename)) {
            \file_put_contents($filename, '%PDF-fake');
        }
    }
}

/** Custom reader for the registerReader / createReaderForFile probing tests. */
final class FakeXmlReader implements \EasyExcel\Compat\Reader\IReader
{
    public function canRead(string $filename): bool
    {
        return \str_ends_with(\strtolower($filename), '.fakexml') && \is_readable($filename);
    }

    public function load(string $filename, int $flags = 0): Spreadsheet
    {
        $s = new Spreadsheet();
        $s->getActiveSheet()->setCellValue('A1', 'from-fake-reader');

        return $s;
    }
}

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

    'shared: StreamPath::isWrapped detects scheme-qualified paths' => function (): void {
        T::ok(StreamPath::isWrapped('php://output'), 'php://');
        T::ok(StreamPath::isWrapped('gaufrette://ftp/report.xlsx'), 'gaufrette://');
        T::ok(StreamPath::isWrapped('s3://bucket/key.xlsx'), 's3://');
        T::ok(StreamPath::isWrapped('file:///tmp/a.xlsx'), 'file:// goes through the stream layer too');
        T::ok(!StreamPath::isWrapped('/tmp/report.xlsx'), 'absolute path');
        T::ok(!StreamPath::isWrapped('report.xlsx'), 'relative path');
        T::ok(!StreamPath::isWrapped('C:\\data\\report.xlsx'), 'windows drive path');
    },

    'writer: custom stream wrapper target staged via temp file' => function (): void {
        \stream_wrapper_register('eextest', EexTestStreamWrapper::class);
        try {
            $s = new Spreadsheet();
            $s->getActiveSheet()->setCellValue('A1', 'wrapped');
            (new XlsxWriter($s))->save('eextest://bucket/report.xlsx');

            [, [, $nativePath]] = EasyExcelFake::calls('save_xlsx')[0];
            T::ok(!\str_contains($nativePath, '://'), 'extension got a real filesystem path');
            $bytes = EexTestStreamWrapper::$files['eextest://bucket/report.xlsx'] ?? '';
            T::ok(\str_contains($bytes, 'wrapped'), 'content reached the wrapper');
        } finally {
            \stream_wrapper_unregister('eextest');
            EexTestStreamWrapper::$files = [];
        }
    },

    'reader: custom stream wrapper source staged via temp file' => function (): void {
        \stream_wrapper_register('eextest', EexTestStreamWrapper::class);
        try {
            EexTestStreamWrapper::$files['eextest://bucket/in.xlsx'] = 'xlsx-bytes';
            try {
                (new XlsxReader())->load('eextest://bucket/in.xlsx');
                T::ok(false, 'fake native open should have failed');
            } catch (\EasyExcel\Exception\EasyExcelException) {
                // expected: the fake extension cannot open workbooks
            }
            [, [$nativePath, , $staged]] = EasyExcelFake::calls('open')[0];
            T::ok(!\str_contains($nativePath, '://'), 'extension got a real filesystem path');
            T::same('xlsx-bytes', $staged, 'staged bytes matched');
            T::ok(!\is_file($nativePath), 'temp file cleaned up');
        } finally {
            \stream_wrapper_unregister('eextest');
            EexTestStreamWrapper::$files = [];
        }
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

    'writer: html repeat table headers' => function (): void {
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->setTitle('Sheet1');
        for ($r = 1; $r <= 6; $r++) {
            $ws->setCellValueByColumnAndRow(1, $r, "Val $r");
        }
        // Set repeating rows 2 to 4
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(2, 4);

        $w = new HtmlWriter($s);
        $html = $w->generateHtmlAll();

        // Should have exactly 2 tables
        T::same(2, \substr_count($html, '<table'), 'table split into 2');
        T::ok(\str_contains($html, '<thead>'), 'thead tag emitted');
        T::ok(\str_contains($html, '</thead>'), 'thead closed');

        // Verify content distribution
        $t1Start = \strpos($html, '<table');
        $t1End = \strpos($html, '</table>', $t1Start);
        $t2Start = \strpos($html, '<table', $t1End);

        T::ok(\strpos($html, 'Val 1') < $t1End, 'Row 1 is in first table');
        T::ok(\strpos($html, 'Val 2') > $t2Start, 'Row 2 is in second table');
        T::ok(\strpos($html, 'Val 2') < \strpos($html, '</thead>'), 'Row 2 is inside thead');
        T::ok(\strpos($html, 'Val 4') < \strpos($html, '</thead>'), 'Row 4 is inside thead');
        T::ok(\strpos($html, 'Val 5') > \strpos($html, '</thead>'), 'Row 5 is after thead (in tbody)');
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

    'iofactory: registerWriter adds and overrides formats' => function (): void {
        $s = new Spreadsheet();

        IOFactory::registerWriter('Pdf', FakePdfWriter::class);
        $w = IOFactory::createWriter($s, 'Pdf');
        T::ok($w instanceof FakePdfWriter, 'registered format resolved');
        T::ok($w->book === $s, 'workbook passed to custom writer');

        IOFactory::registerWriter('Html', FakePdfWriter::class);
        try {
            T::ok(IOFactory::createWriter($s, 'Html') instanceof FakePdfWriter, 'built-in format overridden');
        } finally {
            IOFactory::registerWriter('Html', HtmlWriter::class); // restore for later cases
        }
        T::ok(IOFactory::createWriter($s, 'Html') instanceof HtmlWriter, 'override restored');

        T::throws(
            \EasyExcel\Compat\Writer\Exception::class,
            static fn () => IOFactory::registerWriter('Bogus', \stdClass::class)
        );
    },

    'iofactory: registerReader and createReaderForFile probing' => function (): void {
        IOFactory::registerReader('FakeXml', FakeXmlReader::class);
        T::ok(IOFactory::createReader('FakeXml') instanceof FakeXmlReader, 'registered reader resolved');

        T::throws(
            \EasyExcel\Compat\Reader\Exception::class,
            static fn () => IOFactory::registerReader('Bogus', \stdClass::class)
        );

        // unknown extension: probing must reach the registered reader before Csv's catch-all
        $file = \tempnam(\sys_get_temp_dir(), 'eex') . '.fakexml';
        \file_put_contents($file, '<fake/>');
        try {
            T::ok(IOFactory::createReaderForFile($file) instanceof FakeXmlReader, 'probed via canRead');
            $loaded = IOFactory::load($file);
            T::same('from-fake-reader', $loaded->getActiveSheet()->getCell('A1')->getValue(), 'load() uses probing');
        } finally {
            @\unlink($file);
        }

        // known extension keeps the extension fast path
        $xlsx = \tempnam(\sys_get_temp_dir(), 'eex') . '.xlsx';
        \file_put_contents($xlsx, 'stub');
        try {
            T::ok(
                IOFactory::createReaderForFile($xlsx) instanceof \EasyExcel\Compat\Reader\Xlsx,
                'extension-identified reader'
            );
        } finally {
            @\unlink($xlsx);
        }
    },

    'iofactory: readers implement IReader and new aliases resolve' => function (): void {
        T::ok(new \EasyExcel\Compat\Reader\Xlsx() instanceof \EasyExcel\Compat\Reader\IReader, 'xlsx reader contract');
        T::ok(new CsvReader() instanceof \EasyExcel\Compat\Reader\IReader, 'csv reader contract');
        T::ok(\interface_exists('PhpOffice\\PhpSpreadsheet\\Reader\\IReader'), 'IReader aliased');
        T::ok(\class_exists('PhpOffice\\PhpSpreadsheet\\Writer\\Exception'), 'Writer\\Exception aliased');
        T::ok(\class_exists('PhpOffice\\PhpSpreadsheet\\Reader\\Exception'), 'Reader\\Exception aliased');
    },
];
