<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet;

use EasyExcel\Compat\Cell\Cell;
use EasyExcel\Compat\Cell\Coordinate;

/**
 * Iterates the cells of one row.
 *
 * @implements \Iterator<string, Cell>
 */
class RowCellIterator implements \Iterator
{
    private int $position;

    private int $startColumn;

    private int $endColumn;

    public function __construct(
        private Worksheet $worksheet,
        private int $rowIndex,
        string $startColumn = 'A',
        ?string $endColumn = null,
    ) {
        $this->startColumn = Coordinate::columnIndexFromString($startColumn);
        $endColumn ??= $worksheet->getHighestColumn();
        $this->endColumn = Coordinate::columnIndexFromString($endColumn);
        $this->position = $this->startColumn;
    }

    public function rewind(): void
    {
        $this->position = $this->startColumn;
    }

    public function current(): Cell
    {
        return new Cell($this->worksheet, Coordinate::stringFromColumnIndex($this->position) . $this->rowIndex);
    }

    public function key(): string
    {
        return Coordinate::stringFromColumnIndex($this->position);
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
        return $this->position >= $this->startColumn && $this->position <= $this->endColumn;
    }
}
