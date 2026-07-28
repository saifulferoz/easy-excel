<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Reader;

/**
 * PhpSpreadsheet 5.8+ reader contract: an IReadFilter-aware reader that can also
 * enumerate a workbook's sheets without materialising it.
 *
 * Upstream splits these listing methods out of IReader into IReader2 so that
 * lightweight readers may implement loading without committing to worksheet
 * introspection. The Compat layer mirrors the split for type-compatibility;
 * consumers type-hinting IReader2 accept our readers unchanged.
 */
interface IReader2
{
    /**
     * Loads a workbook from the given file into a new spreadsheet.
     *
     * @param int $flags reader-specific option bitmask (0 = defaults)
     */
    public function load(string $filename, int $flags = 0): \EasyExcel\Compat\Spreadsheet;

    /**
     * Returns per-worksheet metadata (name, dimensions, sheet state) without
     * loading cell data.
     *
     * @return list<array<string, mixed>>
     */
    public function listWorksheetInfo(string $filename): array;

    /**
     * Returns the ordered list of worksheet names.
     *
     * @return list<string>
     */
    public function listWorksheetNames(string $filename): array;
}
