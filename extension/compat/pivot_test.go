package compat

import "testing"

func TestTranslatePivotTable(t *testing.T) {
	opts, err := TranslatePivotTable("Report", `{"name":"Sales","sourceSheet":"Data","sourceRange":"$A$1:$D$9",
		"location":"B3:B3",
		"rows":[{"field":"Region"}],"columns":[{"field":"Product"}],"filters":[{"field":"Year"}],
		"data":[{"field":"Amount","subtotal":"average","caption":"Average of Amount"}]}`)
	if err != nil {
		t.Fatal(err)
	}
	if opts.DataRange != "Data!A1:D9" {
		t.Errorf("data range = %q", opts.DataRange)
	}
	if opts.PivotTableRange != "Report!B3:C4" {
		t.Errorf("pivot range = %q, want a single cell widened to 2x2", opts.PivotTableRange)
	}
	if opts.Name != "Sales" || len(opts.Rows) != 1 || opts.Rows[0].Data != "Region" ||
		opts.Columns[0].Data != "Product" || opts.Filter[0].Data != "Year" {
		t.Errorf("axes not mapped: %+v", opts)
	}
	if d := opts.Data[0]; d.Data != "Amount" || d.Subtotal != "average" || d.Name != "Average of Amount" {
		t.Errorf("data field = %+v", d)
	}
	if !opts.RowGrandTotals || !opts.ColGrandTotals || opts.PivotTableStyleName != "PivotStyleLight16" {
		t.Errorf("PhpSpreadsheet writer defaults not applied: %+v", opts)
	}
}

func TestTranslatePivotTableKeepsExplicitRange(t *testing.T) {
	opts, err := TranslatePivotTable("Report", `{"name":"P","sourceSheet":"Data","sourceRange":"A1:B5",
		"location":"A3:D20","data":[{"field":"Amount"}]}`)
	if err != nil {
		t.Fatal(err)
	}
	if opts.PivotTableRange != "Report!A3:D20" {
		t.Errorf("pivot range = %q", opts.PivotTableRange)
	}
	if opts.Data[0].Subtotal != "sum" {
		t.Errorf("subtotal defaults to sum, got %q", opts.Data[0].Subtotal)
	}
}

func TestTranslatePivotTableRejects(t *testing.T) {
	for name, spec := range map[string]string{
		"bad json":        `{`,
		"no name":         `{"sourceSheet":"Data","sourceRange":"A1:B5","location":"A3","data":[{"field":"x"}]}`,
		"no data field":   `{"name":"P","sourceSheet":"Data","sourceRange":"A1:B5","location":"A3","rows":[{"field":"x"}]}`,
		"bad subtotal":    `{"name":"P","sourceSheet":"Data","sourceRange":"A1:B5","location":"A3","data":[{"field":"x","subtotal":"median"}]}`,
		"bad source":      `{"name":"P","sourceSheet":"Data","sourceRange":"nope","location":"A3","data":[{"field":"x"}]}`,
		"bad location":    `{"name":"P","sourceSheet":"Data","sourceRange":"A1:B5","location":"","data":[{"field":"x"}]}`,
		"bang in sheet":   `{"name":"P","sourceSheet":"Da!ta","sourceRange":"A1:B5","location":"A3","data":[{"field":"x"}]}`,
		"no source sheet": `{"name":"P","sourceRange":"A1:B5","location":"A3","data":[{"field":"x"}]}`,
	} {
		if _, err := TranslatePivotTable("Report", spec); err == nil {
			t.Errorf("%s: expected an error", name)
		}
	}
}
