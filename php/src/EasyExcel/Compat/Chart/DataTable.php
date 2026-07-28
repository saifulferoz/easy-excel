<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Chart;

/**
 * PhpSpreadsheet 5.9-compatible chart data table: the grid of series values
 * rendered beneath a chart's plot area. Attach it to a {@see PlotArea} via
 * {@see PlotArea::setDataTable()}.
 *
 * easy-excel honours the outline/keys toggles natively (excelize's
 * ShowDataTable / ShowDataTableKeys); the horizontal/vertical border flags are
 * stored for API parity but map onto excelize's single outline toggle — see
 * {@see Chart::buildSpec()}.
 */
class DataTable
{
    public function __construct(
        private bool $showHorizontalBorder = true,
        private bool $showVerticalBorder = true,
        private bool $showOutline = true,
        private bool $showKeys = true,
    ) {
    }

    public function getShowHorizontalBorder(): bool
    {
        return $this->showHorizontalBorder;
    }

    public function setShowHorizontalBorder(bool $showHorizontalBorder): static
    {
        $this->showHorizontalBorder = $showHorizontalBorder;

        return $this;
    }

    public function getShowVerticalBorder(): bool
    {
        return $this->showVerticalBorder;
    }

    public function setShowVerticalBorder(bool $showVerticalBorder): static
    {
        $this->showVerticalBorder = $showVerticalBorder;

        return $this;
    }

    public function getShowOutline(): bool
    {
        return $this->showOutline;
    }

    public function setShowOutline(bool $showOutline): static
    {
        $this->showOutline = $showOutline;

        return $this;
    }

    public function getShowKeys(): bool
    {
        return $this->showKeys;
    }

    public function setShowKeys(bool $showKeys): static
    {
        $this->showKeys = $showKeys;

        return $this;
    }
}
