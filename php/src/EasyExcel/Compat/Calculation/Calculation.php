<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Calculation;

use EasyExcel\Compat\Spreadsheet;

/**
 * Calculation-engine facade. Calculation is delegated to excelize, so the
 * cache controls are accepted as no-ops: they are performance hints that
 * cannot change output (COMPAT.md).
 */
class Calculation
{
    private static ?self $instance = null;

    private bool $cacheEnabled = true;

    public static function getInstance(?Spreadsheet $spreadsheet = null): self
    {
        return self::$instance ??= new self();
    }

    public function disableCalculationCache(): void
    {
        $this->cacheEnabled = false;
    }

    public function enableCalculationCache(): void
    {
        $this->cacheEnabled = true;
    }

    public function setCalculationCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    public function getCalculationCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    public function clearCalculationCache(): void
    {
    }

    public function flushInstance(): void
    {
    }
}
