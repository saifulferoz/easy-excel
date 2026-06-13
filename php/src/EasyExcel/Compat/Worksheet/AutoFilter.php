<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet;

/**
 * Auto-filter facade: the range set this session (column rules are wave 4.4).
 */
class AutoFilter
{
    public function __construct(private Worksheet $worksheet)
    {
    }

    public function getRange(): string
    {
        return $this->worksheet->autoFilterRange();
    }

    public function setRange(string $range): static
    {
        $this->worksheet->setAutoFilter($range);

        return $this;
    }
}
