<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet;

/** One worksheet row, yielding a cell iterator over its columns. */
class Row
{
    public function __construct(private Worksheet $worksheet, private int $rowIndex)
    {
    }

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    public function getWorksheet(): Worksheet
    {
        return $this->worksheet;
    }

    public function getCellIterator(string $startColumn = 'A', ?string $endColumn = null, bool $iterateOnlyExistingCells = false): RowCellIterator
    {
        return new RowCellIterator($this->worksheet, $this->rowIndex, $startColumn, $endColumn);
    }
}
