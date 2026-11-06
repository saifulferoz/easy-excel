package core

import (
	"encoding/json"
	"fmt"

	"github.com/xiidea/easy-excel/extension/compat"
)

// Pivot tables (PhpSpreadsheet 5.10 Worksheet\PivotTable). PhpSpreadsheet
// regenerates its in-memory pivot tables on every save, so this is a
// replace-the-set op rather than an append: the shim sends a sheet's full
// list of generated tables, and the tables written for that sheet by an
// earlier save are deleted first. Tables that came in with a loaded file are
// never touched. Resending an unchanged set is a no-op: excelize's
// DeletePivotTable unlinks a table but leaves its parts in the package, so
// replacing only when something changed keeps the common save-twice case
// clean (COMPAT.md divergence 34). Like the rest of the non-streamable surface the op queues
// and applies on flush (COMPAT.md divergence 11); excelize reads the source
// header row at apply time, which is after every stream has been flushed.

// pivotSet records what the last applied op wrote to a sheet.
type pivotSet struct {
	spec  string   // the op's JSON, to detect an unchanged resend
	names []string // tables to delete before the next replacement
}

// SetPivotTables queues the replacement of sheet's generated pivot tables
// with jsonSpecs (a JSON list, see compat.TranslatePivotTable). Specs are
// validated now so a bad table fails at the call site.
func (w *Workbook) SetPivotTables(sheet, jsonSpecs string) error {
	var specs []json.RawMessage
	if err := json.Unmarshal([]byte(jsonSpecs), &specs); err != nil {
		return fmt.Errorf("easy-excel: invalid pivot table list: %w", err)
	}
	seen := map[string]bool{}
	for _, raw := range specs {
		opts, err := compat.TranslatePivotTable(sheet, string(raw))
		if err != nil {
			return err
		}
		if seen[opts.Name] {
			return fmt.Errorf("easy-excel: duplicate pivot table name %q on %s", opts.Name, sheet)
		}
		seen[opts.Name] = true
	}
	return w.queueOp(sheet, pendingOp{kind: opPivotTables, s1: jsonSpecs})
}

// applyOpPivotTables executes a queued pivot-table replacement in
// random-access mode.
func (w *Workbook) applyOpPivotTables(sheet string, op pendingOp) error {
	if op.kind != opPivotTables {
		return fmt.Errorf("easy-excel: unhandled op kind %d", op.kind)
	}
	prev, written := w.pivots[sheet]
	if written && prev.spec == op.s1 {
		return nil
	}
	var specs []json.RawMessage
	if err := json.Unmarshal([]byte(op.s1), &specs); err != nil {
		return err
	}
	for _, name := range prev.names {
		if err := w.f.DeletePivotTable(sheet, name); err != nil {
			return err
		}
	}
	delete(w.pivots, sheet)
	set := pivotSet{spec: op.s1}
	for _, raw := range specs {
		opts, err := compat.TranslatePivotTable(sheet, string(raw))
		if err != nil {
			return err
		}
		if err := w.f.AddPivotTable(opts); err != nil {
			return fmt.Errorf("easy-excel: pivot table %q: %w", opts.Name, err)
		}
		set.names = append(set.names, opts.Name)
	}
	if len(set.names) > 0 {
		w.pivots[sheet] = set
	}
	return nil
}
