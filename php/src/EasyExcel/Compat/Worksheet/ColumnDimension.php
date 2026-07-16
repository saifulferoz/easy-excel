<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet;

use EasyExcel\Compat\Cell\Coordinate;
use EasyExcel\Native;

/**
 * Column width / auto-size facade for one column, or — with a null column
 * index, as handed out by Worksheet::getDefaultColumnDimension() — for the
 * sheet-wide default width (sheetFormatPr; free in streaming mode).
 */
class ColumnDimension
{
    private float $width = -1;
    private bool $autoSize = false;

    public function __construct(private Worksheet $worksheet, private ?string $columnIndex)
    {
    }

    public function getColumnIndex(): ?string
    {
        return $this->columnIndex;
    }

    public function setWidth(float|int $width): static
    {
        $this->width = (float) $width;
        if (null === $this->columnIndex) {
            Native::setDefaultColWidth(
                $this->worksheet->getParent()->getHandle(),
                $this->worksheet->getTitle(),
                (float) $width,
            );

            return $this;
        }
        $col = Coordinate::columnIndexFromString($this->columnIndex);
        Native::setColWidth(
            $this->worksheet->getParent()->getHandle(),
            $this->worksheet->getTitle(),
            $col,
            $col,
            (float) $width,
        );

        return $this;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    /** Auto width is approximated at save time by character count (COMPAT.md). */
    public function setAutoSize(bool $autoSize): static
    {
        $this->autoSize = $autoSize;
        // Auto-size on the default dimension is state-only, like
        // PhpSpreadsheet (its Xlsx writer only reads the default width).
        if ($autoSize && null !== $this->columnIndex) {
            $col = Coordinate::columnIndexFromString($this->columnIndex);
            Native::setColAutoSize(
                $this->worksheet->getParent()->getHandle(),
                $this->worksheet->getTitle(),
                $col,
                $col,
            );
        }

        return $this;
    }

    public function getAutoSize(): bool
    {
        return $this->autoSize;
    }
}
