<?php

declare(strict_types=1);

/*
 * PhpSpreadsheet accepts coordinate arrays everywhere it accepts strings:
 *   [col, row]              -> single cell   (Validations::validateCellAddress)
 *   [col1, row1, col2, row2] -> cell range   (Validations::validateCellRange)
 *
 * These cases pin that contract for the Compat surface. The range form is
 * what real generators emit, e.g.
 *   $sheet->getStyle([$columnIndex, $startRow, $columnIndex, $endRow])
 * Truncating it to the anchor cell silently drops styling on every row but
 * the first.
 */

use EasyExcel\Compat\Exception;
use EasyExcel\Compat\Spreadsheet;

return [
    'coordinate arrays: getStyle([c1,r1,c2,r2]) styles the whole range' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $s->getActiveSheet()->getStyle([1, 1, 3, 2])->getFont()->setBold(true);

        $calls = EasyExcelFake::calls('apply_style');
        T::same(1, \count($calls));
        T::same('A1:C2', $calls[0][1][2], 'range must not be truncated to its anchor cell');
    },

    'coordinate arrays: getStyle([col,row]) addresses a single cell' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $s->getActiveSheet()->getStyle([2, 5])->getFont()->setBold(true);

        $calls = EasyExcelFake::calls('apply_style');
        T::same('B5', $calls[0][1][2]);
    },

    'coordinate arrays: mergeCells([c1,r1,c2,r2]) merges the range' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $s->getActiveSheet()->mergeCells([1, 6, 3, 6]);

        $calls = EasyExcelFake::calls('merge_cells');
        T::same('A6:C6', $calls[0][1][2]);
    },

    'coordinate arrays: unmergeCells([c1,r1,c2,r2]) unmerges the range' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $s->getActiveSheet()->unmergeCells([1, 6, 3, 6]);

        $calls = EasyExcelFake::calls('unmerge_cells');
        T::same('A6:C6', $calls[0][1][2]);
    },

    'coordinate arrays: setAutoFilter([c1,r1,c2,r2]) filters the range' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $s->getActiveSheet()->setAutoFilter([1, 1, 4, 10]);

        $calls = EasyExcelFake::calls('auto_filter');
        T::same('A1:D10', $calls[0][1][2]);
        T::same('A1:D10', $s->getActiveSheet()->getAutoFilter()->getRange());
    },

    'coordinate arrays: invalid element count throws' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        T::throws(Exception::class, static function () use ($s): void {
            $s->getActiveSheet()->getStyle([1, 2, 3]);
        }, 'expected Exception for a 3-element coordinate array');
    },
];
