<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet\Sparkline;

use EasyExcel\Compat\Cell\CellAddress;
use EasyExcel\Compat\Cell\Coordinate;
use EasyExcel\Compat\Exception;

/**
 * One sparkline, PhpSpreadsheet 5.10 style: the cell it is drawn in plus the
 * range it plots. Rendering options live on the owning SparklineGroup.
 */
class Sparkline implements \Stringable
{
    /** Uppercased single cell, without `$` markers or a sheet qualifier. */
    private string $location = '';

    /** Plotted range, e.g. `Sheet1!B2:F2` or `B2:F2`, kept as given. */
    private string $dataRange = '';

    public function __construct(CellAddress|string $location = '', string $dataRange = '')
    {
        if ($location !== '') {
            $this->setLocation($location);
        }
        $this->setDataRange($dataRange);
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(CellAddress|string $location): self
    {
        $location = (string) $location;
        if (\str_contains($location, ':')) {
            throw new Exception('Sparkline location must be a single cell, not a range');
        }
        $location = \str_replace('$', '', $location);
        if (\str_contains($location, '!')) {
            $location = \substr($location, \strrpos($location, '!') + 1);
        }
        Coordinate::indexesFromString($location); // throws on a malformed cell
        $this->location = \strtoupper($location);

        return $this;
    }

    public function getDataRange(): string
    {
        return $this->dataRange;
    }

    public function setDataRange(string $dataRange): self
    {
        $this->dataRange = $dataRange;

        return $this;
    }

    public function __toString(): string
    {
        return $this->location;
    }
}
