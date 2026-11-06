<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet\PivotTable;

/**
 * One field of a pivot table, PhpSpreadsheet 5.10 style: which axis the
 * source field sits on and, for value fields, how it is aggregated.
 */
class PivotField
{
    // pivotField@axis values; empty for a field used only as a value or unused
    public const AXIS_ROW = 'axisRow';
    public const AXIS_COLUMN = 'axisCol';
    public const AXIS_PAGE = 'axisPage';
    public const AXIS_VALUES = 'axisValues';
    public const AXIS_NONE = '';

    // dataField@subtotal values
    public const SUBTOTAL_SUM = 'sum';
    public const SUBTOTAL_COUNT = 'count';
    public const SUBTOTAL_AVERAGE = 'average';
    public const SUBTOTAL_MAX = 'max';
    public const SUBTOTAL_MIN = 'min';
    public const SUBTOTAL_PRODUCT = 'product';
    public const SUBTOTAL_COUNT_NUMS = 'countNums';
    public const SUBTOTAL_STD_DEV = 'stdDev';
    public const SUBTOTAL_STD_DEV_P = 'stdDevp';
    public const SUBTOTAL_VAR = 'var';
    public const SUBTOTAL_VAR_P = 'varp';

    private string $axis = self::AXIS_NONE;

    private bool $dataField = false;

    /** Aggregation for a value field; null otherwise. */
    private ?string $subtotal = null;

    /** Display caption for a value field (e.g. "Sum of Amount"); null otherwise. */
    private ?string $dataFieldCaption = null;

    /** @param int $index zero-based position in the pivot cache's field list */
    public function __construct(private readonly int $index, private string $name = '')
    {
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAxis(): string
    {
        return $this->axis;
    }

    public function setAxis(string $axis): self
    {
        $this->axis = $axis;

        return $this;
    }

    public function isDataField(): bool
    {
        return $this->dataField;
    }

    public function setDataField(bool $dataField): self
    {
        $this->dataField = $dataField;

        return $this;
    }

    public function getSubtotal(): ?string
    {
        return $this->subtotal;
    }

    public function setSubtotal(?string $subtotal): self
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getDataFieldCaption(): ?string
    {
        return $this->dataFieldCaption;
    }

    public function setDataFieldCaption(?string $dataFieldCaption): self
    {
        $this->dataFieldCaption = $dataFieldCaption;

        return $this;
    }
}
