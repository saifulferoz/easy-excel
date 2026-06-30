<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Writer;

use EasyExcel\Compat\Spreadsheet;

/**
 * Writer contract, PhpSpreadsheet-compatible. Lets user code type-hint against
 * the writer surface and supply custom writers (extend {@see BaseWriter} or
 * implement this directly). The built-in Csv/Xlsx writers implement it.
 *
 * The include-charts / pre-calculate / disk-caching accessors exist for
 * source compatibility; the easy-excel extension renders through Go/excelize
 * and does not consume those flags (see COMPAT.md).
 */
interface IWriter
{
    /** Save any charts that are defined (passed to {@see save()} via $flags). */
    public const SAVE_WITH_CHARTS = 1;

    /** Skip formula pre-calculation on save (passed to {@see save()} via $flags). */
    public const DISABLE_PRECALCULATE_FORMULAE = 2;

    public function __construct(Spreadsheet $spreadsheet);

    public function getIncludeCharts(): bool;

    /** @return $this */
    public function setIncludeCharts(bool $includeCharts): self;

    public function getPreCalculateFormulas(): bool;

    /** @return $this */
    public function setPreCalculateFormulas(bool $precalculateFormulas): self;

    /**
     * @param resource|string $filename filesystem path, php:// URL, or open stream
     * @param int             $flags    bitmask of self::SAVE_WITH_CHARTS / self::DISABLE_PRECALCULATE_FORMULAE
     */
    public function save($filename, int $flags = 0): void;

    public function getUseDiskCaching(): bool;

    /** @return $this */
    public function setUseDiskCaching(bool $useDiskCache, ?string $cacheDirectory = null): self;

    public function getDiskCachingDirectory(): string;
}
