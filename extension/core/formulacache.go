package core

import (
	"strconv"

	"github.com/xuri/excelize/v2"
)

// Pre-computed formula cache (Phase 5 cross-cutting item 1).
//
// PhpSpreadsheet evaluates formulas and stores the result alongside the
// formula, so `<f>SUM(A1:A2)</f><v>5</v>`. excelize writes the formula only.
// Excel, LibreOffice and getCalculatedValue() all recompute on open and show
// the right answer, but a headless reader that trusts the cached <v> — which
// is what PDF/HTML rendering pipelines typically use — sees a blank cell.
//
// Two independent mitigations live here, because they address different
// readers and have very different costs:
//
//   - FullCalcOnLoad (cheap, default ON): sets the workbook's
//     calcPr/@fullCalcOnLoad flag, telling a *spreadsheet application* to
//     recalculate everything when it opens the file. Costs one XML attribute
//     and nothing at write time. Does nothing for a reader that never
//     calculates.
//   - PrecalculateFormulas (expensive, default OFF): evaluates every formula
//     cell and writes the result into the cache, matching PhpSpreadsheet.
//     This is what a non-calculating reader needs. It is O(formula cells),
//     forces random-access mode, and so forfeits streaming — hence opt-in.
//
// Scope limit: only **numeric** results are cached. See cacheFormulaResults.

// SetFullCalcOnLoad toggles the workbook's fullCalcOnLoad flag.
func (w *Workbook) SetFullCalcOnLoad(on bool) error {
	w.mu.Lock()
	defer w.mu.Unlock()
	if w.closed {
		return errClosed
	}
	w.fullCalcOnLoad = on
	return nil
}

// SetPrecalculateFormulas enables the save-time evaluate-and-cache pass.
func (w *Workbook) SetPrecalculateFormulas(on bool) error {
	w.mu.Lock()
	defer w.mu.Unlock()
	if w.closed {
		return errClosed
	}
	w.precalculate = on
	return nil
}

// applyCalcProps writes the fullCalcOnLoad flag. Called from the save path.
func (w *Workbook) applyCalcProps() error {
	if !w.fullCalcOnLoad {
		return nil
	}
	on := true
	return w.f.SetCalcProps(&excelize.CalcPropsOptions{FullCalcOnLoad: &on})
}

// cacheFormulaResults evaluates every formula cell and stores the result
// beside the formula, so readers that do not calculate still show a value.
//
// **Numeric results only.** excelize has no way to write a cached string or
// boolean result correctly: SetCellStr/SetCellValue(string) store a
// shared-string *index* in <v>, and the subsequent SetCellFormula rewrites the
// type attribute to "str" while leaving that index in place — the cell then
// reads back as "0" or "1" instead of its text. Caching a wrong value is worse
// than caching none, so text and boolean results are left uncached and keep
// today's recompute-on-open behaviour. Errors (#DIV/0! and friends) are
// skipped for the same reason.
//
// The caller must hold w.mu and have already flushed streams: the formulas
// have to exist in the model before they can be read back and evaluated.
func (w *Workbook) cacheFormulaResults() (int, error) {
	cached := 0
	for _, name := range w.f.GetSheetList() {
		rows, err := w.f.GetRows(name)
		if err != nil {
			return cached, err
		}
		for r := range rows {
			for c := range rows[r] {
				cell, err := excelize.CoordinatesToCellName(c+1, r+1)
				if err != nil {
					return cached, err
				}
				formula, err := w.f.GetCellFormula(name, cell)
				if err != nil || formula == "" {
					continue
				}
				value, err := w.f.CalcCellValue(name, cell)
				if err != nil {
					// An unsupported function or a genuine #VALUE!: leave the
					// cell to be recomputed on open rather than baking in a
					// wrong or empty result.
					continue
				}
				number, err := strconv.ParseFloat(value, 64)
				if err != nil {
					continue // non-numeric — see the docblock
				}
				// Order matters: the value write clears the formula, so the
				// formula is restored immediately after. excelize keeps the
				// cached <v> when a formula is set on a cell that has one.
				if err := w.f.SetCellValue(name, cell, number); err != nil {
					return cached, err
				}
				if err := w.f.SetCellFormula(name, cell, formula); err != nil {
					return cached, err
				}
				cached++
			}
		}
	}
	return cached, nil
}
