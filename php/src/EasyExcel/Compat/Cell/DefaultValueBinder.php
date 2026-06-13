<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Cell;

use EasyExcel\Compat\Shared\Date;

/**
 * Default value binder, mirroring both PhpSpreadsheet's DefaultValueBinder
 * and the Go-side binding semantics (extension/compat/values.go), so custom
 * binders extending it behave identically with and without the extension.
 */
class DefaultValueBinder implements IValueBinder
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            $cell->setValueExplicit(Date::dateTimeToExcel($value), DataType::TYPE_NUMERIC);

            return true;
        }
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }
        $cell->setValueExplicit($value, static::dataTypeForValue($value));

        return true;
    }

    public static function dataTypeForValue(mixed $value): string
    {
        if ($value === null) {
            return DataType::TYPE_NULL;
        }
        if (\is_bool($value)) {
            return DataType::TYPE_BOOL;
        }
        if (\is_int($value) || \is_float($value)) {
            return DataType::TYPE_NUMERIC;
        }
        if (\is_string($value)) {
            if (\strlen($value) > 1 && $value[0] === '=') {
                return DataType::TYPE_FORMULA;
            }
            if (\preg_match('/^[+-]?(\d+\.?\d*|\d*\.?\d+)([eE][+-]?\d+)?$/', $value)) {
                // leading-zero strings stay strings ("0123"), like the Go binder
                $digits = \ltrim($value, '+-');
                if (!(\strlen($digits) > 1 && $digits[0] === '0' && ($digits[1] ?? '') !== '.')) {
                    return DataType::TYPE_NUMERIC;
                }
            }

            return DataType::TYPE_STRING;
        }

        return DataType::TYPE_STRING;
    }
}
