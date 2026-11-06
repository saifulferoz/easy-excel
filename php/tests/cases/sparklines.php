<?php

declare(strict_types=1);

use EasyExcel\Compat\Cell\CellAddress;
use EasyExcel\Compat\Exception;
use EasyExcel\Compat\Worksheet\Sparkline\Sparkline;
use EasyExcel\Compat\Worksheet\Sparkline\SparklineType;

return [
    'sparklines: SparklineType cases carry the OOXML type values' => function (): void {
        T::same('line', SparklineType::Line->value);
        T::same('column', SparklineType::Column->value);
        T::same('stacked', SparklineType::WinLoss->value, 'Win/Loss is "stacked" in OOXML');
        T::same(SparklineType::WinLoss, SparklineType::from('stacked'));
    },

    'sparklines: SparklineType is reachable under the PhpOffice name' => function (): void {
        T::same(
            SparklineType::Column,
            \PhpOffice\PhpSpreadsheet\Worksheet\Sparkline\SparklineType::Column,
            'enum cases are identical through the alias',
        );
    },

    // -------------------------------------------------------------- Sparkline

    'sparklines: Sparkline normalises its location' => function (): void {
        $sp = new Sparkline('Sheet1!$g$2', 'Sheet1!B2:F2');
        T::same('G2', $sp->getLocation(), 'sheet qualifier and $ stripped, uppercased');
        T::same('Sheet1!B2:F2', $sp->getDataRange(), 'data range kept verbatim');
        T::same('G2', (string) $sp);
    },

    'sparklines: Sparkline accepts a CellAddress' => function (): void {
        $sp = new Sparkline(CellAddress::fromCellAddress('H5'), 'B5:F5');
        T::same('H5', $sp->getLocation());
    },

    'sparklines: Sparkline defaults to an empty location' => function (): void {
        $sp = new Sparkline();
        T::same('', $sp->getLocation());
        T::same('', $sp->getDataRange());
        T::ok($sp->setLocation('a1')->setDataRange('B1:C1') === $sp, 'fluent setters');
        T::same('A1', $sp->getLocation());
    },

    'sparklines: Sparkline rejects a range or malformed location' => function (): void {
        T::throws(Exception::class, fn () => new Sparkline('G2:G3', 'B2:F2'), 'range');
        T::throws(Exception::class, fn () => (new Sparkline())->setLocation('not-a-cell'), 'garbage');
    },
];
