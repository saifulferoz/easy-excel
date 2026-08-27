<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Writer;

use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Compat\Worksheet\PageSetup;

/**
 * PDF writer: the HTML renderer plus the page-setup surface that
 * PhpSpreadsheet's Pdf writers expose.
 *
 * PhpSpreadsheet ships `Writer\Pdf` as an abstract base with Mpdf/Tcpdf/Dompdf
 * subclasses that each embed a PHP rendering engine. This writer deliberately
 * stops one step earlier: it produces the print-ready HTML and hands the
 * HTML->PDF conversion to whatever the application already runs — wkhtmltopdf
 * via knplabs/knp-snappy, a headless browser, or a CLI converter. That keeps
 * the polyfill free of a bundled PDF engine while giving the same public API
 * consuming code already calls (getPaperSize/setPaperSize, getOrientation/
 * setOrientation, getFont/setFont, getTempDir/setTempDir).
 *
 * Typical use with knp-snappy:
 *
 *     $writer = IOFactory::createWriter($spreadsheet, 'Pdf');
 *     $writer->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
 *     $pdfBytes = $snappy->getOutputFromHtml($writer->generateHtmlAll());
 *
 * `save()` writes the HTML, so a caller that wants PDF bytes on disk should
 * convert `generateHtmlAll()` itself rather than relying on `save()`.
 */
class Pdf extends Html
{
    /**
     * Paper size names, keyed by the PageSetup::PAPERSIZE_* constant.
     *
     * Mirrors PhpSpreadsheet's own xRef list so that callers reading it back
     * (or subclasses overriding it) see the values they expect. Sizes with no
     * standard name carry their [width, height] in points.
     *
     * @var array<int, float[]|string>
     */
    protected static array $paperSizes = [
        PageSetup::PAPERSIZE_LETTER => 'LETTER',
        PageSetup::PAPERSIZE_LETTER_SMALL => 'LETTER',
        PageSetup::PAPERSIZE_TABLOID => [792.00, 1224.00],
        PageSetup::PAPERSIZE_LEDGER => [1224.00, 792.00],
        PageSetup::PAPERSIZE_LEGAL => 'LEGAL',
        PageSetup::PAPERSIZE_STATEMENT => [396.00, 612.00],
        PageSetup::PAPERSIZE_EXECUTIVE => 'EXECUTIVE',
        PageSetup::PAPERSIZE_A3 => 'A3',
        PageSetup::PAPERSIZE_A4 => 'A4',
        PageSetup::PAPERSIZE_A4_SMALL => 'A4',
        PageSetup::PAPERSIZE_A5 => 'A5',
        PageSetup::PAPERSIZE_B4 => 'B4',
        PageSetup::PAPERSIZE_B5 => 'B5',
        PageSetup::PAPERSIZE_FOLIO => 'FOLIO',
        PageSetup::PAPERSIZE_QUARTO => [609.45, 779.53],
        PageSetup::PAPERSIZE_STANDARD_1 => [720.00, 1008.00],
        PageSetup::PAPERSIZE_STANDARD_2 => [792.00, 1224.00],
        PageSetup::PAPERSIZE_NOTE => 'LETTER',
    ];

    /** Temporary storage directory. */
    protected string $tempDir = '';

    /** Font. */
    protected string $font = 'freesans';

    /** Orientation override; null means "use the sheet's own page setup". */
    protected ?string $orientation = null;

    /** Paper size override; null means "use the sheet's own page setup". */
    protected ?int $paperSize = null;

    /**
     * The workbook being written.
     *
     * Html keeps its own copy private, so hold a second reference rather than
     * widening the parent's property.
     */
    private Spreadsheet $spreadsheetForPdf;

    public function __construct(Spreadsheet $spreadsheet)
    {
        parent::__construct($spreadsheet);
        $this->spreadsheetForPdf = $spreadsheet;
        $this->tempDir = \sys_get_temp_dir() . '/phpsppdf';
    }

    public function getFont(): string
    {
        return $this->font;
    }

    /**
     * Set the font. Valid values are the ones the downstream renderer knows;
     * they are stored verbatim and never interpreted here.
     */
    public function setFont(string $fontName): static
    {
        $this->font = $fontName;

        return $this;
    }

    /** The paper-size override, or null when the sheet's page setup wins. */
    public function getPaperSize(): ?int
    {
        return $this->paperSize;
    }

    public function setPaperSize(int $paperSize): static
    {
        $this->paperSize = $paperSize;

        return $this;
    }

    /** The orientation override, or null when the sheet's page setup wins. */
    public function getOrientation(): ?string
    {
        return $this->orientation;
    }

    public function setOrientation(string $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    public function setTempDir(string $temporaryDirectory): static
    {
        $this->tempDir = $temporaryDirectory;

        return $this;
    }

    /**
     * The paper size that should be used, as a name ('A4', 'LETTER', ...) or a
     * [width, height] pair in points.
     *
     * Resolves the override first, then the active sheet's page setup, then
     * PhpSpreadsheet's own default of Letter. Handy for building the argument
     * list for an external converter.
     *
     * @return float[]|string
     */
    public function resolvePaperSize(): array|string
    {
        $size = $this->paperSize ?? $this->activeSheetPageSetup()->getPaperSize();

        return self::$paperSizes[$size] ?? 'LETTER';
    }

    /**
     * The orientation that should be used: 'portrait' or 'landscape'.
     *
     * Resolves the override first, then the active sheet's page setup. A sheet
     * left on ORIENTATION_DEFAULT reports portrait, matching what every PDF
     * engine assumes when no orientation is given.
     */
    public function resolveOrientation(): string
    {
        $orientation = $this->orientation ?? $this->activeSheetPageSetup()->getOrientation();

        return $orientation === PageSetup::ORIENTATION_LANDSCAPE
            ? PageSetup::ORIENTATION_LANDSCAPE
            : PageSetup::ORIENTATION_PORTRAIT;
    }

    private function activeSheetPageSetup(): PageSetup
    {
        $index = $this->getSheetIndex();
        $sheet = $index === null
            ? $this->spreadsheetForPdf->getActiveSheet()
            : $this->spreadsheetForPdf->getSheet($index);

        return $sheet->getPageSetup();
    }
}
