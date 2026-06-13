<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet;

/** One worksheet column, yielding a cell iterator over its rows. */
class Column
{
    public function __construct(private Worksheet $worksheet, private string $columnIndex)
    {
    }

    public function getColumnIndex(): string
    {
        return $this->columnIndex;
    }

    public function getWorksheet(): Worksheet
    {
        return $this->worksheet;
    }

    public function getCellIterator(int $startRow = 1, ?int $endRow = null, bool $iterateOnlyExistingCells = false): ColumnCellIterator
    {
        return new ColumnCellIterator($this->worksheet, $this->columnIndex, $startRow, $endRow);
    }
}
