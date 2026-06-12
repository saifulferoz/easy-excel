# Native ABI reference — using easy-excel without the polyfill

The extension exposes a flat, handle-based C ABI of `easy_excel_*()`
functions. The [`easy-excel/polyfill`](https://github.com/xiidea/easy-excel-polyfill)
composer package is a convenience layer over it (PhpSpreadsheet classes,
write-behind batching); everything it does can be done directly with the
functions below — no composer dependency at all. Use this path for maximum
throughput, for custom wrappers, or in codebases that never used
PhpSpreadsheet.

All you need is a FrankenPHP built with the extension
(`ghcr.io/xiidea/frankenphp8.5-easy-excel`); check with
`function_exists('easy_excel_new')`.

## Conventions

**Handles.** Workbooks live in Go; PHP holds an opaque random `int` handle.
Always `easy_excel_close()` when done — a 10-minute idle TTL reclaims leaked
handles, but relying on it wastes memory budget.

**Error encoding.** The bridge cannot throw, so:

- *Mutating* functions return `?string` — `null` **or `''`** on success
  (Go's nil marshals as an empty PHP string), an error message otherwise.
- *Value* functions return a pair `array{0: mixed, 1: ?string}` — index 0 is
  the value, index 1 the error (same null-or-empty success rule).

Error messages are prefixed for typed handling: `OVERLOADED:` (admission
control / memory budget — back off or 429), `DENIED:` (path policy),
`BADHANDLE:` (closed or unknown handle). Anything else is a plain failure.

```php
function ee_check(?string $err): void {
    if ($err !== null && $err !== '') { throw new RuntimeException($err); }
}
function ee_unwrap(array $pair): mixed {
    ee_check($pair[1] ?? null);
    return $pair[0];
}
```

**Cell value encoding.** Cells are PHP scalars (auto-bound with
PhpSpreadsheet's DefaultValueBinder semantics: numeric strings become
numbers except leading-zero ones, `=…` becomes a formula) or explicit
`[marker, value]` pairs that defeat auto-binding:

| Marker | Meaning |
|---|---|
| `'=s'` | string (even if it looks numeric or starts with `=`) |
| `'=n'` | numeric (e.g. an Excel date serial) |
| `'=b'` | boolean |
| `'=f'` | formula (leading `=` optional) |

`null` skips the cell.

**Batching.** Each call crosses the CGO boundary (~µs). The write path is
designed around `easy_excel_write_rows` with hundreds–thousands of rows per
call; per-cell `easy_excel_set_cell` is the slow path (it also ends
streaming mode — see *Streaming* below).

**Streaming.** Fresh workbooks stream ascending row writes at constant
memory. Structure/style calls are coordinate-based and order-independent:
apply styles, widths, panes, merges, formats **before** writing the rows
they affect and everything rides the stream. Reads, out-of-order writes, or
styling already-written rows trigger the documented one-time degrade
(COMPAT.md §4/§9). Auto-filter is special-cased and never degrades on its
own (COMPAT.md §16).

## Lifecycle

```php
easy_excel_version(): string                  // extension version
easy_excel_new(): array                       // -> [handle, err] fresh workbook, one sheet "Worksheet"
easy_excel_open(string $path): array          // -> [handle, err] load .xlsx (path policy applies)
easy_excel_close(int $handle): ?string        // release; closing a stale handle is a no-op
easy_excel_stats(): array                     // [liveHandles, estMemoryBytes] for monitoring
```

## Sheets

```php
easy_excel_add_sheet(int $handle, string $name): array            // -> [index, err]
easy_excel_delete_sheet(int $handle, string $name): ?string       // last sheet cannot be removed
easy_excel_rename_sheet(int $handle, string $old, string $new): ?string
easy_excel_sheets(int $handle): array                             // -> [list<string>, err]
easy_excel_set_active_sheet(int $handle, int $index): ?string     // 0-based position
easy_excel_active_sheet(int $handle): array                       // -> [[position, name], err]
```

## Writing

```php
// THE hot path: rows is a packed list of packed lists of cell values
// (scalars, [marker, value] pairs, or null to skip), anchored at
// (startRow, startCol), both 1-based.
easy_excel_write_rows(int $handle, string $sheet, int $startRow, int $startCol, array $rows): ?string

// single cell; value is [scalar] or [marker, scalar]
easy_excel_set_cell(int $handle, string $sheet, string $cell, array $value): ?string
```

## Reading

```php
// mode: 0 = raw (typed: float/bool; formulas come back as "=..."),
//       1 = formatted (number formats rendered),
//       2 = calculated (excelize engine, see FORMULAS.md)
easy_excel_get_cell(int $handle, string $sheet, string $cell, int $mode): array  // -> [value, err]

// chunked sequential reads reuse a forward iterator: loop with startRow +=
// count until more === false. raw=false renders formats; calc=true
// evaluates formula cells that have no cached result.
easy_excel_read_rows(int $handle, string $sheet, int $startRow, int $maxRows, bool $raw, bool $calc): array
// -> [[rows: list<list<string>>, more: bool], err]

easy_excel_dimensions(int $handle, string $sheet): array  // -> [[highestRow, highestCol], err]
```

## Styling & structure

Ranges accept `"B2"`, `"A1:C10"`, or full columns `"C"` / `"C:E"`.

```php
easy_excel_apply_style(int $handle, string $sheet, string $range, string $styleJson): ?string
easy_excel_set_number_format(int $handle, string $sheet, string $range, string $code): ?string
easy_excel_merge_cells(int $handle, string $sheet, string $range): ?string
easy_excel_set_col_width(int $handle, string $sheet, int $startCol, int $endCol, float $width): ?string
easy_excel_set_col_autosize(int $handle, string $sheet, int $startCol, int $endCol): ?string  // approximated at save
easy_excel_set_row_height(int $handle, string $sheet, int $row, float $height): ?string
easy_excel_freeze_panes(int $handle, string $sheet, string $topLeftCell): ?string  // "" or "A1" unfreezes
easy_excel_auto_filter(int $handle, string $sheet, string $range): ?string
easy_excel_set_hyperlink(int $handle, string $sheet, string $cell, string $url, string $tooltip): ?string
easy_excel_set_comment(int $handle, string $sheet, string $cell, string $author, string $text): ?string
easy_excel_defined_name(int $handle, string $name, string $refersTo, string $scopeSheet): ?string
easy_excel_page_setup(int $handle, string $sheet, string $orientation, int $paperSize, int $fitToWidth, int $fitToHeight): ?string
// orientation "" keeps current; paperSize <= 0 and fit* < 0 mean "not set"
```

`$styleJson` is PhpSpreadsheet's `applyFromArray` shape, JSON-encoded.
Partial styles for the same/containing ranges merge in application order:

```php
$style = [
    'font'         => ['bold' => true, 'size' => 12, 'name' => 'Arial',
                       'underline' => 'single', 'color' => ['rgb' => 'FFFFFF']],
    'fill'         => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
    'borders'      => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '333333']]],
    'alignment'    => ['horizontal' => 'center', 'vertical' => 'top', 'wrapText' => true],
    'numberFormat' => ['formatCode' => '#,##0.00'],
    'protection'   => ['locked' => 'unprotected'],
];
ee_check(easy_excel_apply_style($h, 'Worksheet', 'A1:F1', json_encode($style)));
```

## Validation, conditional formats, images, protection, charts

All take JSON specs; invalid specs fail at the call site, application
happens at save (COMPAT.md §11).

```php
easy_excel_set_validation(int $handle, string $sheet, string $range, string $validationJson): ?string
// {"type":"list","formula1":"open,paid,void","allowBlank":true,
//  "showErrorMessage":true,"errorTitle":"...","error":"..."}
// types: list (literal or range formula1) | whole | decimal | date | time |
//        textLength | custom; operators: between, notBetween, equal,
//        notEqual, greaterThan(OrEqual), lessThan(OrEqual)

easy_excel_set_conditional(int $handle, string $sheet, string $range, string $rulesJson): ?string
// JSON list, e.g.
// [{"type":"cellIs","operator":"greaterThan","conditions":["5"],
//   "style":{"font":{"bold":true},"fill":{"fillType":"solid","startColor":{"rgb":"FFC7CE"}}}},
//  {"type":"colorScale","colorScale":{"minColor":"FF0000","maxColor":"00FF00"}},
//  {"type":"dataBar","dataBar":{"color":"638EC6"}},
//  {"type":"expression","conditions":["$B1>100"]}]

easy_excel_add_image(int $handle, string $sheet, string $cell, string $imageJson): ?string
// {"path":"/data/logo.png","name":"Logo","offsetX":5,"offsetY":5,
//  "width":120,"height":0}   width/height px; 0 keeps natural size,
//                            one side set keeps aspect ratio

easy_excel_protect_sheet(int $handle, string $sheet, string $protectionJson): ?string
// {"sheet":true,"password":"...","formatCells":true,...}
// PhpSpreadsheet polarity: true = that action is locked

easy_excel_add_chart(int $handle, string $sheet, string $cell, string $chartJson): ?string
// {"type":"col","title":"Totals",
//  "series":[{"name":"Worksheet!$B$1","categories":"Worksheet!$A$2:$A$9",
//             "values":"Worksheet!$B$2:$B$9"}],
//  "legend":{"position":"bottom"},"width":600,"height":300}
// types: area, bar, barStacked, col, colStacked, doughnut, line, pie,
//        radar, scatter
```

## Saving

```php
easy_excel_save_xlsx(int $handle, string $path): ?string
// delimiter is one character; guardFormulas enables the opt-in OWASP
// formula-injection guard (prefixes risky cells with ')
easy_excel_save_csv(int $handle, string $path, string $sheet, string $delimiter, bool $crlf, bool $bom, bool $guardFormulas): ?string
```

The workbook stays usable after saving (further writes use random-access
mode). For `php://output`-style targets, save to a temp file and stream it.

## Complete example

```php
<?php
// no composer, no polyfill — just the extension
[$h, $err] = easy_excel_new();
ee_check($err);

try {
    // structure first, so everything streams
    ee_check(easy_excel_apply_style($h, 'Worksheet', 'A1:C1', json_encode([
        'font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DDEBF7']],
    ])));
    ee_check(easy_excel_set_number_format($h, 'Worksheet', 'C', '#,##0.00'));
    ee_check(easy_excel_set_col_width($h, 'Worksheet', 1, 1, 24.0));
    ee_check(easy_excel_freeze_panes($h, 'Worksheet', 'A2'));

    ee_check(easy_excel_write_rows($h, 'Worksheet', 1, 1, [['Customer', 'Status', 'Total']]));

    $batch = [];
    $row = 2;
    foreach ($orders as $order) {                    // millions of rows: constant memory
        $batch[] = [$order->customer, $order->status, $order->total];
        if (\count($batch) === 2048) {
            ee_check(easy_excel_write_rows($h, 'Worksheet', $row, 1, $batch));
            $row += 2048;
            $batch = [];
        }
    }
    if ($batch) {
        ee_check(easy_excel_write_rows($h, 'Worksheet', $row, 1, $batch));
        $row += \count($batch);
    }

    ee_check(easy_excel_auto_filter($h, 'Worksheet', 'A1:C' . ($row - 1)));
    ee_check(easy_excel_save_xlsx($h, '/data/report.xlsx'));
} finally {
    easy_excel_close($h);
}
```

## Operational notes

- Environment configuration (concurrency gate, memory budget, path
  allowlist) is shared with the polyfill — see the
  [README's configuration table](README.md#configuration).
- `easy_excel_stats()` belongs in your health endpoint: live handles should
  return to your steady-state count and estimated memory to ~0 when idle.
- If you want typed exceptions and the unwrap boilerplate without the
  PhpSpreadsheet compat layer, the polyfill's `EasyExcel\Native` class is
  exactly that wrapper and nothing more — installable and usable standalone
  (the aliases stay dormant unless you opt in).
