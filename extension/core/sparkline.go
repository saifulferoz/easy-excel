package core

import (
	"fmt"

	"github.com/xiidea/easy-excel/extension/compat"
)

// Sparklines (PhpSpreadsheet 5.10 Worksheet\Sparkline). excelize writes them
// into the worksheet's extLst, which the StreamWriter does not carry, so they
// queue and apply on flush like the rest of the non-streamable surface
// (COMPAT.md divergence 11).

// AddSparklineGroup queues one sparkline group (JSON spec, see
// compat.TranslateSparklineGroup). The spec is validated now so a bad group
// fails at the call site rather than at save.
func (w *Workbook) AddSparklineGroup(sheet, jsonSpec string) error {
	if _, err := compat.TranslateSparklineGroup(jsonSpec); err != nil {
		return err
	}
	return w.queueOp(sheet, pendingOp{kind: opSparkline, s1: jsonSpec})
}

// applyOpSparkline executes a queued sparkline group in random-access mode.
func (w *Workbook) applyOpSparkline(sheet string, op pendingOp) error {
	if op.kind != opSparkline {
		return fmt.Errorf("easy-excel: unhandled op kind %d", op.kind)
	}
	opts, err := compat.TranslateSparklineGroup(op.s1)
	if err != nil {
		return err
	}
	return w.f.AddSparkline(sheet, opts)
}
