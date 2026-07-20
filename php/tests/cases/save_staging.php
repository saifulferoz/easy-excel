<?php

declare(strict_types=1);

/*
 * Wrapped-target saves stage through a temp file (the extension only writes
 * real paths). The staged name must carry a .xlsx extension: excelize's
 * SaveAs validates it and tempnam() alone produces none — saving a report
 * to gaufrette://... failed with "unsupported workbook file format".
 */

return [
    'xlsx writer: wrapped targets stage under a .xlsx temp name' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'x');

        $writer = new EasyExcel\Compat\Writer\Xlsx($spreadsheet);
        \ob_start();
        try {
            $writer->save('php://output');
        } finally {
            \ob_end_clean();
        }

        [, [, $nativePath]] = EasyExcelFake::calls('save_xlsx')[0];
        T::ok(\str_ends_with($nativePath, '.xlsx'), "staged path lacks .xlsx: {$nativePath}");
        T::ok(!\file_exists($nativePath), 'staged file must be cleaned up');
    },
];
