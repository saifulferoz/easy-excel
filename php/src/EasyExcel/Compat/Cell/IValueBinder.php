<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Cell;

/** Value binder contract, PhpSpreadsheet-compatible. */
interface IValueBinder
{
    public function bindValue(Cell $cell, mixed $value): bool;
}
