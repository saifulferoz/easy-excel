<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet;

use EasyExcel\Compat\Cell\Cell;

/**
 * Iterates the cells of one column.
 *
 * @implements \Iterator<int, Cell>
 */
class ColumnCellIterator implements \Iterator
{
    private int $position;

    private int $endRow;

    public function __construct(
        private Worksheet $worksheet,
        private string $columnIndex,
        private int $startRow = 1,
        ?int $endRow = null,
    ) {
        $this->endRow = $endRow ?? $worksheet->getHighestRow();
        $this->position = $startRow;
    }

    public function rewind(): void
    {
        $this->position = $this->startRow;
    }

    public function current(): Cell
    {
        return new Cell($this->worksheet, $this->columnIndex . $this->position);
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function prev(): void
    {
        --$this->position;
    }

    public function valid(): bool
    {
        return $this->position >= $this->startRow && $this->position <= $this->endRow;
    }
}
