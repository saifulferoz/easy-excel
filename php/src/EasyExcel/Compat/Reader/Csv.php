<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Reader;

use EasyExcel\Compat\Exception;
use EasyExcel\Compat\Spreadsheet;

class Csv implements IReader2
{
    private string $delimiter = ',';
    private string $enclosure = '"';
    protected string $escapeCharacter = '';
    protected bool $testAutodetect = true;
    private int $sheetIndex = 0;

    public function setDelimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function setEnclosure(string $enclosure): static
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    public function getEnclosure(): string
    {
        return $this->enclosure;
    }

    /**
     * Sets the CSV escape character. Defaults to '' (no escaping), matching
     * PhpSpreadsheet 5.x, which passes an empty escape string to fgetcsv().
     */
    public function setEscapeCharacter(string $escapeCharacter, int $version = \PHP_VERSION_ID): static
    {
        $this->escapeCharacter = $escapeCharacter;

        return $this;
    }

    public function getEscapeCharacter(int $version = \PHP_VERSION_ID): string
    {
        return $this->escapeCharacter;
    }

    public function setTestAutoDetect(bool $value): static
    {
        $this->testAutodetect = $value;

        return $this;
    }

    public function getTestAutoDetect(): bool
    {
        return $this->testAutodetect;
    }

    public function setSheetIndex(int $sheetIndex): static
    {
        $this->sheetIndex = $sheetIndex;

        return $this;
    }

    public function canRead(string $filename): bool
    {
        return \is_readable($filename);
    }

    /**
     * A CSV is always a single, unnamed worksheet; report it as sheet index 0.
     *
     * @return list<array<string, mixed>>
     */
    public function listWorksheetInfo(string $filename): array
    {
        [$rows, $cols] = $this->dimensions($filename);
        $lastColumn = $cols > 0 ? \EasyExcel\Compat\Cell\Coordinate::stringFromColumnIndex($cols) : 'A';

        return [[
            'worksheetName' => 'Worksheet',
            'lastColumnLetter' => $lastColumn,
            'lastColumnIndex' => $cols > 0 ? $cols - 1 : 0,
            'totalRows' => $rows,
            'totalColumns' => $cols,
            'sheetState' => 'visible',
        ]];
    }

    /**
     * @return list<string>
     */
    public function listWorksheetNames(string $filename): array
    {
        return ['Worksheet'];
    }

    /**
     * One streaming pass to count rows and the widest column, so listing does
     * not have to materialise the workbook.
     *
     * @return array{0:int, 1:int} [rowCount, maxColumns]
     */
    private function dimensions(string $filename): array
    {
        $fh = @\fopen($filename, 'rb');
        if ($fh === false) {
            throw new Exception("Could not open file $filename for reading.");
        }

        $rows = 0;
        $cols = 0;
        try {
            $bom = \fread($fh, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                \rewind($fh);
            }
            while (($data = \fgetcsv($fh, 0, $this->delimiter, $this->enclosure, $this->escapeCharacter)) !== false) {
                ++$rows;
                $cols = \max($cols, \count($data));
            }
        } finally {
            \fclose($fh);
        }

        return [$rows, $cols];
    }

    /**
     * Streams the CSV into the native workbook in 1k-row chunks: constant
     * PHP memory, sequential rows keep the Go side in streaming mode.
     */
    public function load(string $filename, int $flags = 0): Spreadsheet
    {
        $fh = @\fopen($filename, 'rb');
        if ($fh === false) {
            throw new Exception("Could not open file $filename for reading.");
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        try {
            // skip a UTF-8 BOM if present
            $bom = \fread($fh, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                \rewind($fh);
            }
            $chunk = [];
            $row = 1;
            while (($data = \fgetcsv($fh, 0, $this->delimiter, $this->enclosure, $this->escapeCharacter)) !== false) {
                $chunk[] = $data;
                if (\count($chunk) >= 1000) {
                    $sheet->fromArray($chunk, null, 'A' . $row, true);
                    $row += \count($chunk);
                    $chunk = [];
                }
            }
            if ($chunk !== []) {
                $sheet->fromArray($chunk, null, 'A' . $row, true);
            }
        } finally {
            \fclose($fh);
        }

        return $spreadsheet;
    }
}
