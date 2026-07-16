<?php

declare(strict_types=1);

/*
 * Sheet-wide default dimensions (Worksheet::getDefaultRowDimension() /
 * getDefaultColumnDimension()): a null-index RowDimension/ColumnDimension
 * routing to sheetFormatPr via the set_default_* natives instead of the
 * per-row/per-column calls. Unlike per-row heights, the default never
 * degrades a streaming sheet — reports with a uniform rowHeight should
 * prefer it.
 */

return [
    'default row dimension: setRowHeight routes to sheet default' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $dimension = $worksheet->getDefaultRowDimension();
        $dimension->setRowHeight(20);

        T::same([[1, 'Worksheet', 20.0]], array_column(EasyExcelFake::calls('set_default_row_height'), 1));
        T::same([], EasyExcelFake::calls('set_row_height'), 'must not fall back to per-row heights');
        T::same(20.0, $dimension->getRowHeight());
        T::same(null, $dimension->getRowIndex());
    },

    'default column dimension: setWidth routes to sheet default' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $dimension = $worksheet->getDefaultColumnDimension();
        $dimension->setWidth(14);

        T::same([[1, 'Worksheet', 14.0]], array_column(EasyExcelFake::calls('set_default_col_width'), 1));
        T::same([], EasyExcelFake::calls('set_col_width'), 'must not fall back to per-column widths');
        T::same(14.0, $dimension->getWidth());
        T::same(null, $dimension->getColumnIndex());
    },

    'default dimensions are cached per worksheet' => function (): void {
        $worksheet = (new PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();

        T::ok($worksheet->getDefaultRowDimension() === $worksheet->getDefaultRowDimension(), 'row dimension not cached');
        T::ok($worksheet->getDefaultColumnDimension() === $worksheet->getDefaultColumnDimension(), 'column dimension not cached');
    },

    'default column dimension: setAutoSize is state-only' => function (): void {
        $dimension = (new PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet()
            ->getDefaultColumnDimension()->setAutoSize(true);

        T::same([], EasyExcelFake::calls('set_col_autosize'), 'default dimension must not queue auto-size');
        T::same(null, $dimension->getColumnIndex());
    },

    'per-row and per-column dimensions are unchanged' => function (): void {
        $worksheet = (new PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
        $worksheet->getRowDimension(3)->setRowHeight(30);
        $worksheet->getColumnDimension('B')->setWidth(25);

        T::same([1, 'Worksheet', 3, 30.0], EasyExcelFake::calls('set_row_height')[0][1]);
        T::same([1, 'Worksheet', 2, 2, 25.0], EasyExcelFake::calls('set_col_width')[0][1]);
        T::same(3, $worksheet->getRowDimension(3)->getRowIndex());
        T::same('B', $worksheet->getColumnDimension('B')->getColumnIndex());
    },
];
