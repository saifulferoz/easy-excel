<?php

declare(strict_types=1);

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
];
