# Not implemented

PhpSpreadsheet APIs the polyfill does **not** provide. Found by running real
PhpSpreadsheet code against it (`data/public/index.php` is one such probe);
COMPAT.md documents what *is* supported and where supported behavior
intentionally diverges. Calling anything below fails loudly (class not
found / clear exception) — never with a silently different file.

**An implementation plan for closing these gaps exists**: PLAN.md §13
"Phase 4 — compat completion" orders everything below into four ROI waves
with verified excelize APIs and effort estimates. Items land there and get
deleted here.

## Found by the ERP report probe (`data/public/index.php`)

Closed by wave 4.4 (2026-06-13): rich-text cell values with per-run fonts,
GD `MemoryDrawing`, the PhpSpreadsheet `Chart\*` object model
(`Worksheet::addChart`), and auto-filter column rules
(`getAutoFilter()->getColumn()`). This completes Phase 4 as planned — but a
later audit of two production report apps found further gaps, listed below;
they are a mix of by-design exclusions and unclaimed surface.

Closed by wave 5.3 (2026-08-19): `setBreak()` (+`ByColumnAndRow` and the
`BREAK_*` constants) via `excelize.InsertPageBreak`/`RemovePageBreak`;
`setSelectedCells()` (+aliases), merged into the sheet's pane record so it
composes with `freezePane()` rather than clobbering it; and
`calculateColumnWidths()` as an accepted no-op. 2 new bridge exports (60
total), 10 Go tests, 18 shim tests — 217 passing.

Closed by wave 5.1 (2026-08-19): `Shared\StringHelper` (found by checking
every `PhpOffice\*` import in the four consumer writer files against Compat —
33 of 34 now resolve). Wave 5.1's main work was consumer-side: re-parenting
both apps' `HTMLWriter` from `Writer\Html` to `Writer\BaseWriter`, which the
tokenizer confirmed inherited nothing but the constructor. `Style\Conditional
Formatting\MergedCellStyle` remains the one unresolved writer import — it
needs `StyleMerger` plus table-style resolution, so it is a subsystem, not a
quick win.

Closed by wave 5.2 (2026-08-19): `Writer\Exception`, `Reader\Exception` and
`Calculation\Exception` (narrow types that still satisfy broad catches, and
the writers/readers now throw them); `Reader\IReader` (both readers
implement it); `Settings` (`setChartRenderer` accepted and ignored);
`Cell\CellAddress` + `Cell\AddressRange`; `Worksheet\BaseDrawing` (extracted
as the real parent of `Drawing`/`MemoryDrawing`); and `Shared\File`,
`Shared\Font`, `Shared\Drawing`. 54 new tests, 180 passing overall.

Closed by wave 4.3 (2026-06-13): insert/remove rows and columns,
`createSheet($index)`, sheet copy (`Spreadsheet::copySheet` extra), sheet
views (gridlines/zoom/RTL/tab color), headers/footers, page margins — plus
a correctness fix: post-save mutations were silently dropped by excelize
on stream-flushed sheets; they now reopen first (COMPAT.md §21).

Closed by wave 4.2 (2026-06-13): `getDefaultStyle()`, row/column iterators,
`IReadFilter`, style read-back from loaded files + `duplicateStyle`,
validation/conditional/defined-name/auto-filter getters.

Closed by wave 4.1 (2026-06-13): custom value binders, document properties
(`getProperties()`; `setManager` is kept PHP-side only — excelize has no
field for it), print titles + print area, the `getConditionalStyles()`
getter, workbook encryption (writer/reader `setPassword()`, easy-excel
extras), gradient fills, diagonal borders, `unmergeCells` + merge getter,
and calculation-cache no-ops.

## Known gaps (by area)

Scoped deliberately: this list covers **only APIs actually called by two
production Symfony report apps** audited against the shipped Compat tree
(100 PhpSpreadsheet-using files between them). It is not an exhaustive diff
of PhpSpreadsheet — for that, run `php/tools/compat-surface-diff.php`. Counts
in parentheses are call sites found in each app, so the list doubles as a
priority order.

The alias surface is derived by scanning `php/src/EasyExcel/Compat`, so **a
name without a file there throws `UnsupportedApiException` in `strict`
mode** — and strict is all-or-nothing, so one uncovered class fails the whole
request.

**Writer extension points** (the largest real-world blocker — both apps)
- `Writer\Html` **subclassing** — both apps do `class HTMLWriter extends Html`
  (~1800 and ~1400 lines) overriding protected methods. The public writer API
  is supported, but the Compat writer is an independent pure-PHP renderer,
  not a port, so the inheritance contract does not compose
- `Writer\Pdf` — both apps subclass their `HTMLWriter` to produce PDFs
- `Writer\Xlsx\WriterPart` / `Writer\Xlsx\Worksheet` — excelize owns OOXML
  serialization; custom writer parts cannot be intercepted
- Subclassing `Spreadsheet` / `Worksheet` to inject XML — Compat objects are
  handle facades over Go state, not an extensible PHP object graph

**Charts**
- `Chart\Layout` (15), `Chart\Axis` (9), `Chart\GridLines` (6),
  `Chart\ChartColor` (1) — wave 4.4 mapped `Chart`, `DataSeries`,
  `DataSeriesValues`, `PlotArea`, `Legend` and `Title`; axis config, gridlines
  and manual layout are not
- `Chart\Renderer\JpGraph` — no renderer concept; charts are emitted as
  native Excel chart parts, never rasterized in PHP. (`Settings` itself landed
  in wave 5.2: `setChartRenderer()` is accepted and ignored)

**Worksheet methods**
- `getCellCollection()` (2) — out by design: cell data lives in Go, not in a
  PHP collection. Both call sites are inside the HTML writers that wave 5.1
  decoupled

**Not exercised by either app** (still gaps, lower priority)
- Auto-filter **column rule** introspection (range getter landed in 4.2)
- `removeConditionalStyles`
- `clone $sheet` / `Spreadsheet::addExternalSheet` (use
  `Spreadsheet::copySheet` instead)
- Vertical/horizontal borders (conditional-formatting-only border sides)
- Header/footer images, cell background images (file & memory drawings
  anchored to cells are supported)
- Readers/writers: Ods, Xls, Slk, Gnumeric — install the real
  `phpoffice/phpspreadsheet` alongside (the alias bootstrap stays dormant
  and defers to it) or convert externally
- 63 of PhpSpreadsheet's 529 calculation functions (list in FORMULAS.md)

**Behavioral divergences that bite these two apps**
- **Pre-computed formula cache** — formula cells are written without a
  cached `<v>` result, so spreadsheet apps that don't auto-recalculate on
  open (some headless readers) show them blank until recalculated. Both apps
  feed generated xlsx into PDF/HTML rendering, and the budget variance
  reports are formula-heavy (COMPAT.md §24)
- **Style-after-write degrade** — both apps style subtotal/total rows *after*
  writing them, which queues the work and triggers the one-time
  serialize-and-reopen at save, forfeiting the streaming win on the largest
  reports (COMPAT.md §9)
- Auto-filter does not hide non-matching rows (column rules are recorded;
  Excel re-applies on open — COMPAT.md §23)
- `Calculation` array-formula toggles (the cache controls are accepted
  no-ops since wave 4.1) — calculation is delegated to excelize

## Verified against PhpSpreadsheet

`data/public/index.php` is a self-verifying probe: it generates the ERP
report through the `PhpOffice\PhpSpreadsheet\*` aliases, reloads it, and
diffs the data table against `data/public/phpspreadsheet.xlsx` (the same
report from real PhpSpreadsheet). Run it over HTTP (see `http-verify.sh`):

```
docker run -d -e SERVER_NAME=":80" -v $PWD/data:/app \
  -v $PWD/php:/opt/easy-excel/php -p 8085:80 frankenphp-easy-excel
curl http://localhost:8085/        # → REPORT TEST PASS
```

Want one of these? Open an issue at
[xiidea/easy-excel](https://github.com/xiidea/easy-excel/issues) — gaps get
prioritized by real-world usage, and this file shrinks as they land.
