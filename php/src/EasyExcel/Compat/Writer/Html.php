<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Writer;

use EasyExcel\Compat\Cell\Coordinate;
use EasyExcel\Compat\Exception;
use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Compat\Worksheet\PageSetup;
use EasyExcel\Compat\Worksheet\Worksheet;

/**
 * Renders a workbook to HTML, PhpSpreadsheet-compatible. This is pure PHP (the
 * Go/excelize extension has no HTML output) and works off the formatted cell
 * values exposed by the Compat layer, so it is available with or without the
 * extension.
 *
 * Faithful to the common PhpSpreadsheet surface — sheet tables with merged-cell
 * row/colspans, an optional sheet-navigation block, and the generate*() pieces
 * (header / styles / sheet data / navigation / footer). The fine-grained style,
 * inline-CSS, image-embedding and conditional-formatting knobs are accepted for
 * source compatibility but render with a single shared stylesheet (COMPAT.md).
 */
class Html extends BaseWriter
{
    protected ?int $sheetIndex = 0;

    private bool $generateSheetNavigationBlock = true;

    private bool $useInlineCss = false;

    private bool $embedImages = false;

    private string $imagesRoot = '.';

    private string $lineEnding = PHP_EOL;

    private bool $betterBoolean = true;

    private bool $tableFormats = false;

    private bool $conditionalFormatting = false;

    private bool $dataFormula = false;

    private bool $preserveFormatAndValue = false;

    /** @var null|callable(string):string */
    private $editHtmlCallback;

    public function __construct(private Spreadsheet $spreadsheet)
    {
    }

    /**
     * @param resource|string $filename filesystem path, php:// URL, or open stream
     */
    public function save($filename, int $flags = 0): void
    {
        $this->processFlags($flags);
        $html = $this->generateHtmlAll();

        if (\is_string($filename) && !\str_starts_with($filename, 'php://')) {
            if (\file_put_contents($filename, $html) === false) {
                throw new Exception("Could not write to $filename");
            }

            return;
        }

        $this->openFileHandle($filename);
        \fwrite($this->fileHandle, $html);
        $this->maybeCloseFileHandle();
    }

    /** Full standalone document: header (with styles), navigation, sheet data, footer. */
    public function generateHtmlAll(): string
    {
        $html = $this->generateHTMLHeader(true);
        if ($this->generateSheetNavigationBlock && $this->sheetIndex === null) {
            $html .= $this->generateNavigation();
        }
        $html .= $this->generateSheetData();
        $html .= $this->generateHTMLFooter();

        if ($this->editHtmlCallback !== null) {
            $html = ($this->editHtmlCallback)($html);
        }

        return $html;
    }

    public function generateHTMLHeader(bool $includeStyles = false): string
    {
        $title = $this->spreadsheet->getProperties()->getTitle();
        $eol = $this->lineEnding;

        $html = '<!DOCTYPE html>' . $eol
            . '<html lang="en">' . $eol
            . '<head>' . $eol
            . '<meta charset="UTF-8" />' . $eol
            . '<title>' . self::escape($title !== '' ? $title : 'Spreadsheet') . '</title>' . $eol;
        if ($includeStyles) {
            $html .= $this->generateStyles(false);
        }
        $html .= '</head>' . $eol
            . '<body>' . $eol;

        return $html;
    }

    public function generateStyles(bool $generateSurroundingHTML = true): string
    {
        $eol = $this->lineEnding;
        $css = 'body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }' . $eol
            . 'table.sheet { border-collapse: collapse; margin-bottom: 1em; }' . $eol
            . 'table.sheet caption { font-weight: bold; text-align: left; padding: 0.25em 0; }' . $eol
            . 'table.sheet td { border: 1px solid #d0d0d0; padding: 1px 3px; vertical-align: top; }' . $eol
            . 'nav.sheet-navigation a { margin-right: 1em; }' . $eol;

        if (!$generateSurroundingHTML) {
            return '<style type="text/css">' . $eol . $css . '</style>' . $eol;
        }

        return $this->generateHTMLHeader(false)
            . '<style type="text/css">' . $eol . $css . '</style>' . $eol
            . $this->generateHTMLFooter();
    }

    public function generateNavigation(): string
    {
        $eol = $this->lineEnding;
        $html = '<nav class="sheet-navigation">' . $eol;
        foreach ($this->spreadsheet->getAllSheets() as $i => $sheet) {
            $html .= '<a href="#sheet' . $i . '">' . self::escape($sheet->getTitle()) . '</a>' . $eol;
        }
        $html .= '</nav>' . $eol;

        return $html;
    }

    public function generateSheetData(): string
    {
        $html = '';
        foreach ($this->sheetsToRender() as $index => $sheet) {
            $html .= $this->generateTable($sheet, $index);
        }

        return $html;
    }

    public function generateHTMLFooter(): string
    {
        return '</body>' . $this->lineEnding . '</html>' . $this->lineEnding;
    }

    // -- accessors (PhpSpreadsheet-compatible) ---------------------------------

    public function getSheetIndex(): ?int
    {
        return $this->sheetIndex;
    }

    public function setSheetIndex(int $sheetIndex): static
    {
        $this->sheetIndex = $sheetIndex;

        return $this;
    }

    /** Render every sheet instead of a single one. */
    public function writeAllSheets(): static
    {
        $this->sheetIndex = null;

        return $this;
    }

    public function getGenerateSheetNavigationBlock(): bool
    {
        return $this->generateSheetNavigationBlock;
    }

    public function setGenerateSheetNavigationBlock(bool $generateSheetNavigationBlock): static
    {
        $this->generateSheetNavigationBlock = $generateSheetNavigationBlock;

        return $this;
    }

    public function getUseInlineCss(): bool
    {
        return $this->useInlineCss;
    }

    public function setUseInlineCss(bool $useInlineCss): static
    {
        $this->useInlineCss = $useInlineCss;

        return $this;
    }

    public function getEmbedImages(): bool
    {
        return $this->embedImages;
    }

    public function setEmbedImages(bool $embedImages): static
    {
        $this->embedImages = $embedImages;

        return $this;
    }

    public function getImagesRoot(): string
    {
        return $this->imagesRoot;
    }

    public function setImagesRoot(string $imagesRoot): static
    {
        $this->imagesRoot = $imagesRoot;

        return $this;
    }

    public function getLineEnding(): string
    {
        return $this->lineEnding;
    }

    public function setLineEnding(string $lineEnding): self
    {
        $this->lineEnding = $lineEnding;

        return $this;
    }

    public function getTableFormats(): bool
    {
        return $this->tableFormats;
    }

    public function setTableFormats(bool $tableFormats, ?bool $tableFormatsBuiltin = null): self
    {
        $this->tableFormats = $tableFormats;

        return $this;
    }

    public function getConditionalFormatting(): bool
    {
        return $this->conditionalFormatting;
    }

    public function setConditionalFormatting(bool $conditionalFormatting): self
    {
        $this->conditionalFormatting = $conditionalFormatting;

        return $this;
    }

    public function getBetterBoolean(): bool
    {
        return $this->betterBoolean;
    }

    public function setBetterBoolean(bool $betterBoolean): self
    {
        $this->betterBoolean = $betterBoolean;

        return $this;
    }

    public function setDataFormula(bool $dataFormula): self
    {
        $this->dataFormula = $dataFormula;

        return $this;
    }

    public function setPreserveFormatAndValue(bool $preserveFormatAndValue): self
    {
        $this->preserveFormatAndValue = $preserveFormatAndValue;

        return $this;
    }

    public function setEditHtmlCallback(?callable $callback): void
    {
        $this->editHtmlCallback = $callback;
    }

    /** Page orientation of the rendered sheet, or null when left at default. */
    public function getOrientation(): ?string
    {
        $sheet = $this->spreadsheet->getSheet($this->sheetIndex ?? 0);
        $orientation = $sheet->getPageSetup()->getOrientation();

        return $orientation === PageSetup::ORIENTATION_DEFAULT ? null : $orientation;
    }

    // -- internals -------------------------------------------------------------

    /** @return array<int, Worksheet> sheets to render keyed by their workbook index */
    private function sheetsToRender(): array
    {
        if ($this->sheetIndex === null) {
            return $this->spreadsheet->getAllSheets();
        }

        return [$this->sheetIndex => $this->spreadsheet->getSheet($this->sheetIndex)];
    }

    private function generateTable(Worksheet $sheet, int $index): string
    {
        $eol = $this->lineEnding;
        $rows = $sheet->toArray(null, true, true, false);
        $spans = $this->mergeSpans($sheet);

        $html = '<table class="sheet" id="sheet' . $index . '">' . $eol
            . '<caption>' . self::escape($sheet->getTitle()) . '</caption>' . $eol;

        foreach ($rows as $r => $cells) {
            $rowNum = $r + 1; // toArray() is a 0-indexed list; cells are 1-based
            $html .= '<tr>' . $eol;
            foreach ($cells as $c => $value) {
                $colNum = $c + 1;
                $span = $spans[$rowNum][$colNum] ?? null;
                if ($span === 'covered') {
                    continue; // swallowed by a merge anchor
                }
                $attr = '';
                if (\is_array($span)) {
                    if ($span['cols'] > 1) {
                        $attr .= ' colspan="' . $span['cols'] . '"';
                    }
                    if ($span['rows'] > 1) {
                        $attr .= ' rowspan="' . $span['rows'] . '"';
                    }
                }
                $cell = $value === null ? '' : self::escape((string) $value);
                $html .= '<td' . $attr . '>' . ($cell === '' ? '&nbsp;' : $cell) . '</td>' . $eol;
            }
            $html .= '</tr>' . $eol;
        }

        return $html . '</table>' . $eol;
    }

    /**
     * Resolve merged ranges into per-cell span info: the top-left anchor carries
     * {rows, cols}; every other covered cell is marked 'covered' so it is skipped.
     *
     * @return array<int, array<int, array{rows:int, cols:int}|string>>
     */
    private function mergeSpans(Worksheet $sheet): array
    {
        $spans = [];
        foreach ($sheet->getMergeCells() as $range) {
            [[$startCol, $startRow], [$endCol, $endRow]] = Coordinate::rangeBoundaries($range);
            $spans[$startRow][$startCol] = ['rows' => $endRow - $startRow + 1, 'cols' => $endCol - $startCol + 1];
            for ($row = $startRow; $row <= $endRow; ++$row) {
                for ($col = $startCol; $col <= $endCol; ++$col) {
                    if ($row === $startRow && $col === $startCol) {
                        continue;
                    }
                    $spans[$row][$col] = 'covered';
                }
            }
        }

        return $spans;
    }

    private static function escape(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
