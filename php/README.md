# easy-excel/polyfill

PhpSpreadsheet-compatible PHP API backed by the **easy-excel** FrankenPHP
extension — spreadsheet heavy lifting (XML, ZIP, parsing, formulas) runs in
compiled Go via [excelize](https://github.com/qax-os/excelize), while your
code keeps using the `PhpOffice\PhpSpreadsheet\*` classes it already knows.

This package is the PHP half of [xiidea/easy-excel](https://github.com/xiidea/easy-excel)
(the Go extension, build pipeline, benchmarks and design docs live there).
Versions are tagged in lockstep: `vX.Y.Z` here matches `vX.Y.Z` of the
extension and the `ghcr.io/xiidea/frankenphp8.5-easy-excel:X.Y.Z` image.

## Why

Measured on the reference workload (write N rows × 10 mixed columns, PHP 8.5):

| Library | 100k rows | 1M rows | Peak PHP memory |
|---|---|---|---|
| PhpSpreadsheet 5.8 | 14.74s | — | 665MB at 100k |
| OpenSpout 4.x | 3.64s | 36.74s | 4MB |
| fast-excel-writer 6.x | 2.67s | 28.16s | 4MB |
| **easy-excel** | **0.82s** | **7.85s** | **4MB** |

PhpSpreadsheet ergonomics at OpenSpout-class constant memory, several times
faster than both. Styled-report numbers and the full methodology are in the
[main repository](https://github.com/xiidea/easy-excel#measured-performance).

## Requirements

- PHP ≥ 8.3 running on **FrankenPHP built with the easy-excel extension**.
  The easiest way is the published image:

  ```bash
  docker pull ghcr.io/xiidea/frankenphp8.5-easy-excel:latest
  ```

- Without the extension the package stays **dormant**: nothing is aliased,
  nothing breaks. The same is true when the real `phpoffice/phpspreadsheet`
  is installed — adoption can be incremental.

## Install

```bash
composer require easy-excel/polyfill
```

The published image also ships this package at `/opt/easy-excel/php`, usable
as a path repository if you prefer pinning to the image:

```json
{
    "repositories": [{ "type": "path", "url": "/opt/easy-excel/php" }],
    "require": { "easy-excel/polyfill": "*" }
}
```

## Usage

Existing PhpSpreadsheet code works unchanged — the bootstrap lazily aliases
`PhpOffice\PhpSpreadsheet\*` to the native-backed `EasyExcel\Compat\*`
classes:

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// style first, then bulk-write: everything below streams at constant memory
$sheet->getStyle('A1:C1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
]);
$sheet->getStyle('B2:B100000')->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getColumnDimension('A')->setWidth(28);
$sheet->freezePane('A2');

$sheet->fromArray($hugeDataset);          // batched straight into Go
$sheet->setAutoFilter('A1:C100000');      // injected at save, still streaming

IOFactory::createWriter($spreadsheet, 'Xlsx')->save('report.xlsx');
$spreadsheet->disconnectWorksheets();     // frees the native workbook
```

Reading works the same way (`IOFactory::load`, `toArray`, `rangeToArray`,
`getCalculatedValue` — formulas are evaluated by excelize's engine, 466/529
PhpSpreadsheet functions, [coverage table](https://github.com/xiidea/easy-excel/blob/main/FORMULAS.md)).

You can also use the `EasyExcel\Compat\*` classes directly, or the flat
`EasyExcel\Native` ABI wrapper for maximum throughput.

## What's covered

- **Workbook/worksheet**: create/load/save (Xlsx, Csv), sheet management,
  `setCellValue(+Explicit)`, `fromArray`/`toArray`/`rangeToArray`, dimensions
- **Styles**: font, fill, borders, alignment, protection, number formats —
  `applyFromArray` and the full `getStyle(range)` graph; styles applied
  before their rows are written ride the stream at zero cost
- **Structure**: column widths/auto-size, row heights, merged cells,
  auto-filter, freeze panes, hyperlinks, comments, defined names, page setup
- **Advanced**: data validation, conditional formatting, images, sheet
  protection, charts (native declarative API), formula evaluation
- **Load control**: heavy operations pass a semaphore + memory budget;
  overload surfaces as `EasyExcel\Exception\Overloaded` (map it to HTTP 429)
  instead of OOM-killing the worker

The precise matrix and every documented divergence:
[COMPAT.md](https://github.com/xiidea/easy-excel/blob/main/COMPAT.md).

## Configuration (environment)

| Env var | Default | Meaning |
|---|---|---|
| `EASY_EXCEL_MAX_CONCURRENT` | `max(2, NumCPU)` | heavy ops (open/save/scan) in flight |
| `EASY_EXCEL_ACQUIRE_TIMEOUT_MS` | `30000` | wait before `Overloaded` is raised |
| `EASY_EXCEL_MEMORY_BUDGET_MB` | `512` | live-workbook memory circuit breaker |
| `EASY_EXCEL_ALLOWED_PATHS` | unset (any local path) | colon-separated base dirs for load/save |
| `EASY_EXCEL_FORCE_ALIAS` | unset | `1` forces the aliases even when phpspreadsheet is installed (used by the test suite) |

## Tests

```bash
composer test    # zero-dependency suite against an in-memory fake of the ABI
```

The end-to-end suite (real extension, Docker) runs in the
[main repository's CI](https://github.com/xiidea/easy-excel/actions).

## License

MIT
