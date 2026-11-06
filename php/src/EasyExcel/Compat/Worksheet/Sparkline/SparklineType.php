<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet\Sparkline;

/**
 * Sparkline group type, PhpSpreadsheet 5.10 style. The backing values are the
 * OOXML `x14:sparklineGroup/@type` strings; Win/Loss is stored as `stacked`.
 */
enum SparklineType: string
{
    case Line = 'line';

    case Column = 'column';

    case WinLoss = 'stacked';
}
