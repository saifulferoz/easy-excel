package core

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/xuri/excelize/v2"

	"github.com/xiidea/easy-excel/extension/compat"
)

// setFormula writes a formula through the normal cell-value path: the shim
// marks formulas with a leading '=', exactly as a PHP caller would.
func setFormula(t *testing.T, w *Workbook, cell, formula string) {
	t.Helper()
	col, row, err := excelize.CellNameToCoordinates(cell)
	if err != nil {
		t.Fatal(err)
	}
	if err := w.WriteRows("Worksheet", row, col, [][]compat.Cell{
		mustCells(t, "="+formula),
	}); err != nil {
		t.Fatal(err)
	}
}

func writeFormulaBook(t *testing.T, w *Workbook) {
	t.Helper()
	rows := [][]compat.Cell{
		mustCells(t, 2.5, 3.25),
	}
	if err := w.WriteRows("Worksheet", 1, 1, rows); err != nil {
		t.Fatal(err)
	}
	setFormula(t, w, "C1", "SUM(A1:B1)")
	setFormula(t, w, "D1", "A1*B1")
}

func TestFullCalcOnLoadIsOnByDefault(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "fullcalc.xlsx")
	writeFormulaBook(t, w)
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	assertWorkbookXMLContains(t, path, `fullCalcOnLoad="true"`)
}

func TestFullCalcOnLoadCanBeDisabled(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetFullCalcOnLoad(false); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "nofullcalc.xlsx")
	writeFormulaBook(t, w)
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	assertWorkbookXMLLacks(t, path, `fullCalcOnLoad="true"`)
}

// The default must not silently change streaming behaviour: fullCalcOnLoad is
// one workbook-level attribute and must never force a degrade.
func TestFullCalcOnLoadDoesNotDegrade(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "stream.xlsx")
	fillRows(t, w, "Worksheet", 1, 500)
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	if w.Degraded() {
		t.Fatal("the default fullCalcOnLoad flag must not cost streaming")
	}
}

func TestPrecalculateIsOffByDefault(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "nocache.xlsx")
	writeFormulaBook(t, w)
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	// formula present, no cached value
	assertSheetXMLContains(t, path, `<f>SUM(A1:B1)</f>`)
	f := reopen(t, path)
	raw, _ := f.GetCellValue("Worksheet", "C1", excelize.Options{RawCellValue: true})
	if raw != "" {
		t.Errorf("without precalculate the cache must stay empty, got %q", raw)
	}
}

func TestPrecalculateWritesTheCachedValue(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "cache.xlsx")
	writeFormulaBook(t, w)
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}

	f := reopen(t, path)
	for cell, want := range map[string]string{"C1": "5.75", "D1": "8.125"} {
		formula, err := f.GetCellFormula("Worksheet", cell)
		if err != nil {
			t.Fatal(err)
		}
		if formula == "" {
			t.Errorf("%s: formula was destroyed by the cache write", cell)
		}
		raw, err := f.GetCellValue("Worksheet", cell, excelize.Options{RawCellValue: true})
		if err != nil {
			t.Fatal(err)
		}
		if raw != want {
			t.Errorf("%s cached = %q, want %q", cell, raw, want)
		}
	}
}

// The cached value must equal what recalculating produces — a stale or wrong
// cache is worse than none, since readers trust it.
func TestPrecalculateCacheAgreesWithRecalculation(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "agree.xlsx")

	if err := w.WriteRows("Worksheet", 1, 1, [][]compat.Cell{
		mustCells(t, 7.0, 3.0),
	}); err != nil {
		t.Fatal(err)
	}
	formulas := map[string]string{
		"C1": "A1/B1",
		"D1": "ROUND(A1/B1,3)",
		"E1": "MAX(A1:B1)",
		"F1": "A1-B1*2",
	}
	for cell, f := range formulas {
		setFormula(t, w, cell, f)
	}
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}

	f := reopen(t, path)
	for cell := range formulas {
		cachedValue, err := f.GetCellValue("Worksheet", cell, excelize.Options{RawCellValue: true})
		if err != nil {
			t.Fatal(err)
		}
		live, err := f.CalcCellValue("Worksheet", cell)
		if err != nil {
			t.Fatal(err)
		}
		if cachedValue != live {
			t.Errorf("%s: cached %q but recalculates to %q", cell, cachedValue, live)
		}
	}
}

// Non-numeric results are deliberately left uncached: excelize would store a
// shared-string index in <v>, which reads back as a wrong number.
func TestPrecalculateSkipsNonNumericResults(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "text.xlsx")

	if err := w.WriteRows("Worksheet", 1, 1, [][]compat.Cell{
		mustCells(t, 5.0),
	}); err != nil {
		t.Fatal(err)
	}
	setFormula(t, w, "B1", `IF(A1>1,"big","small")`)
	setFormula(t, w, "C1", "SUM(A1:A1)")
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}

	f := reopen(t, path)
	// the text formula keeps its formula and stays uncached...
	formula, _ := f.GetCellFormula("Worksheet", "B1")
	if formula == "" {
		t.Error("text formula must survive")
	}
	if v, _ := f.CalcCellValue("Worksheet", "B1"); v != "big" {
		t.Errorf("text formula recalculates to %q, want big", v)
	}
	// ...while the numeric one beside it is cached
	raw, _ := f.GetCellValue("Worksheet", "C1", excelize.Options{RawCellValue: true})
	if raw != "5" {
		t.Errorf("numeric formula cached = %q, want 5", raw)
	}
}

// An unevaluable formula must not poison the save.
func TestPrecalculateToleratesErrorFormulas(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "err.xlsx")

	if err := w.WriteRows("Worksheet", 1, 1, [][]compat.Cell{
		mustCells(t, 1.0, 0.0),
	}); err != nil {
		t.Fatal(err)
	}
	setFormula(t, w, "C1", "A1/B1")
	setFormula(t, w, "D1", "SUM(A1:B1)")
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatalf("a #DIV/0! formula must not fail the save: %v", err)
	}
	f := reopen(t, path)
	if raw, _ := f.GetCellValue("Worksheet", "D1", excelize.Options{RawCellValue: true}); raw != "1" {
		t.Errorf("the healthy formula beside the error must still cache, got %q", raw)
	}
}

// Precalculation forfeits streaming *when there are formulas to calculate*;
// the default path never does. (A formula-free export is covered by
// TestPrecalculateIsFreeWithoutFormulas.)
func TestPrecalculateDegradesButDefaultDoesNot(t *testing.T) {
	streamed, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer streamed.Close()
	fillRows(t, streamed, "Worksheet", 1, 300)
	setFormula(t, streamed, "D301", "SUM(B1:B300)")
	if err := streamed.SaveXlsx(filepath.Join(t.TempDir(), "a.xlsx"), ""); err != nil {
		t.Fatal(err)
	}
	if streamed.Degraded() {
		t.Error("default path must keep streaming even with a formula present")
	}

	precalc, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer precalc.Close()
	if err := precalc.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	fillRows(t, precalc, "Worksheet", 1, 300)
	setFormula(t, precalc, "D301", "SUM(B1:B300)")
	if err := precalc.SaveXlsx(filepath.Join(t.TempDir(), "b.xlsx"), ""); err != nil {
		t.Fatal(err)
	}
	if !precalc.Degraded() {
		t.Error("precalculate must degrade once a formula exists — it reads every formula back")
	}
}

// A pure-data export must keep streaming even with precalculate on: there is
// nothing to calculate, so the flag should cost nothing.
func TestPrecalculateIsFreeWithoutFormulas(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	fillRows(t, w, "Worksheet", 1, 400)
	if err := w.SaveXlsx(filepath.Join(t.TempDir(), "data.xlsx"), ""); err != nil {
		t.Fatal(err)
	}
	if w.Degraded() {
		t.Error("precalculate must not degrade a workbook with no formulas")
	}
}

func assertWorkbookXMLContains(t *testing.T, path, needle string) {
	t.Helper()
	if !workbookXML(t, path, needle) {
		t.Errorf("workbook.xml does not contain %s", needle)
	}
}

func assertWorkbookXMLLacks(t *testing.T, path, needle string) {
	t.Helper()
	if workbookXML(t, path, needle) {
		t.Errorf("workbook.xml unexpectedly contains %s", needle)
	}
}

func workbookXML(t *testing.T, path, needle string) bool {
	t.Helper()
	f := reopen(t, path)
	// excelize exposes the parsed flag; assert through the public API so the
	// test does not depend on attribute ordering in the raw XML.
	opts, err := f.GetCalcProps()
	if err != nil {
		t.Fatal(err)
	}
	on := opts.FullCalcOnLoad != nil && *opts.FullCalcOnLoad
	return on == strings.Contains(needle, "true")
}

// Blocker 2 (review): CalcCellValue returns the *formatted* value unless
// RawCellValue is set, so a number-formatted total ("2,500") fails ParseFloat
// and is silently skipped — exactly the report cell the cache exists for.
func TestPrecalculateCachesNumberFormattedCells(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "numfmt.xlsx")

	if err := w.WriteRows("Worksheet", 1, 1, [][]compat.Cell{
		mustCells(t, 1000.0, 1500.0),
	}); err != nil {
		t.Fatal(err)
	}
	setFormula(t, w, "C1", "SUM(A1:B1)")
	// #,##0 — the format a report total actually carries.
	if err := w.ApplyStyle("Worksheet", "C1", `{"numberFormat":{"formatCode":"#,##0"}}`); err != nil {
		t.Fatal(err)
	}
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}

	f := reopen(t, path)
	raw, err := f.GetCellValue("Worksheet", "C1", excelize.Options{RawCellValue: true})
	if err != nil {
		t.Fatal(err)
	}
	if raw != "2500" {
		t.Errorf("number-formatted formula cached = %q, want 2500 (thousands separator must not defeat the cache)", raw)
	}
}

// Blocker 3 (review): precalculate defaulted on and degraded any workbook that
// had ever written a formula — a million-row streamed export with one total
// row flipped to a full in-memory model plus a []][]string copy of every
// sheet, silently. Streaming is the library's headline property; it must not
// be traded away without the caller asking.
func TestPrecalculateIsOptInNotDefault(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	// no SetPrecalculateFormulas call at all
	fillRows(t, w, "Worksheet", 1, 300)
	setFormula(t, w, "D301", "SUM(B1:B300)")
	if err := w.SaveXlsx(filepath.Join(t.TempDir(), "default.xlsx"), ""); err != nil {
		t.Fatal(err)
	}
	if w.Degraded() {
		t.Error("a streamed export with a total row must not degrade by default")
	}
}

// Opening a file must not by itself imply "this workbook has formulas worth
// a full evaluate pass" — every loaded workbook was degrading on save.
func TestPrecalculateOnLoadedFileWithoutFormulas(t *testing.T) {
	src, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "data.xlsx")
	fillRows(t, src, "Worksheet", 1, 50)
	if err := src.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	src.Close()

	w, err := Open(path, "", testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	// A loaded pure-data workbook has nothing to calculate; the pass should be
	// a no-op rather than a full-sheet materialisation.
	n, err := w.cacheFormulaResults()
	if err != nil {
		t.Fatal(err)
	}
	if n != 0 {
		t.Errorf("cached %d formulas in a pure-data workbook, want 0", n)
	}
}

// Documents the excelize limitation behind review blocker 1: SetCellFormula
// ends with an unconditional c.T = "str", so a cached numeric result is
// emitted as text and its number format is not applied by a reader that does
// not recalculate. Pinned so the day excelize gains a way to reset the type,
// this test fails and the workaround can be removed.
func TestPrecalculateCachedCellIsTypedStr(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "typed.xlsx")
	if err := w.WriteRows("Worksheet", 1, 1, [][]compat.Cell{
		mustCells(t, 1000.0, 1500.0),
	}); err != nil {
		t.Fatal(err)
	}
	setFormula(t, w, "C1", "SUM(A1:B1)")
	if err := w.ApplyStyle("Worksheet", "C1", `{"numberFormat":{"formatCode":"#,##0"}}`); err != nil {
		t.Fatal(err)
	}
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}

	f := reopen(t, path)
	// The value is cached and readable...
	raw, _ := f.GetCellValue("Worksheet", "C1", excelize.Options{RawCellValue: true})
	if raw != "2500" {
		t.Errorf("cached value = %q, want 2500", raw)
	}
	// ...but typed as text, so the number format does not render.
	typ, err := f.GetCellType("Worksheet", "C1")
	if err != nil {
		t.Fatal(err)
	}
	if typ != excelize.CellTypeSharedString && typ != excelize.CellTypeInlineString {
		t.Logf("cached cell type = %v; if excelize now preserves the numeric "+
			"type, drop the t=str caveat from COMPAT.md §24", typ)
	}
	if displayed, _ := f.GetCellValue("Worksheet", "C1"); displayed == "2,500" {
		t.Error("number format now applies to cached cells — the documented " +
			"limitation is fixed and COMPAT.md §24 should be updated")
	}
}
