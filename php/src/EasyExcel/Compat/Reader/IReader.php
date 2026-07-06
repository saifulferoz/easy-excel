<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Reader;

use EasyExcel\Compat\Spreadsheet;

/**
 * Reader contract, PhpSpreadsheet-compatible. Lets user code type-hint against
 * the reader surface and supply custom readers via IOFactory::registerReader().
 * The built-in Csv/Xlsx readers implement it.
 */
interface IReader
{
    /** Load charts along with the data (passed to {@see load()} via $flags). */
    public const LOAD_WITH_CHARTS = 1;

    /** Load cell values only, skipping formatting (passed to {@see load()} via $flags). */
    public const READ_DATA_ONLY = 2;

    /** Skip empty cells while loading (passed to {@see load()} via $flags). */
    public const IGNORE_EMPTY_CELLS = 4;

    /** Can this reader read the given file? */
    public function canRead(string $filename): bool;

    /**
     * @param int $flags bitmask of self::LOAD_WITH_CHARTS / self::READ_DATA_ONLY / self::IGNORE_EMPTY_CELLS
     */
    public function load(string $filename, int $flags = 0): Spreadsheet;
}
