<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet;

/**
 * Row iterator (wave 4.2). Rows and cells are coordinate facades — value
 * access goes through the per-cell read path, so for bulk extraction
 * toArray()/rangeToArray() remain the fast path (COMPAT.md).
 *
 * @implements \Iterator<int, Row>
 */
class RowIterator implements \Iterator
{
    private int $position;

    private int $endRow;

    public function __construct(private Worksheet $worksheet, private int $startRow = 1, ?int $endRow = null)
    {
        $this->position = $startRow;
        $this->endRow = $endRow ?? $worksheet->getHighestRow();
    }

    public function rewind(): void
    {
        $this->position = $this->startRow;
    }

    public function current(): Row
    {
        return new Row($this->worksheet, $this->position);
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

    public function seek(int $row): static
    {
        $this->position = $row;

        return $this;
    }

    public function resetEnd(?int $endRow = null): static
    {
        $this->endRow = $endRow ?? $this->worksheet->getHighestRow();

        return $this;
    }
}
