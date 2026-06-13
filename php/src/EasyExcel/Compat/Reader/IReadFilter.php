<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Reader;

/** Read filter contract, PhpSpreadsheet-compatible. */
interface IReadFilter
{
    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool;
}
