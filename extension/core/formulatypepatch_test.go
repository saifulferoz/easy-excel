package core

import (
	"path/filepath"
	"testing"
)

// The patch is a regexp over worksheet XML, so its precision is the safety
// boundary: a false positive relabels a genuine string result as a number.
func TestNumericStrFormulaRegexp(t *testing.T) {
	cases := []struct {
		name  string
		in    string
		want  string
		match bool
	}{
		{
			name:  "numeric cached result loses t=str",
			in:    `<c r="C1" s="1" t="str"><f>SUM(A1:B1)</f><v>2500</v></c>`,
			want:  `<c r="C1" s="1"><f>SUM(A1:B1)</f><v>2500</v></c>`,
			match: true,
		},
		{
			name:  "negative value",
			in:    `<c r="C1" t="str"><f>A1-B1</f><v>-0.75</v></c>`,
			want:  `<c r="C1"><f>A1-B1</f><v>-0.75</v></c>`,
			match: true,
		},
		{
			name:  "scientific notation",
			in:    `<c r="C1" t="str"><f>A1*B1</f><v>1.5E+10</v></c>`,
			want:  `<c r="C1"><f>A1*B1</f><v>1.5E+10</v></c>`,
			match: true,
		},
		{
			name:  "leading-dot decimal",
			in:    `<c r="C1" t="str"><f>A1/B1</f><v>.5</v></c>`,
			want:  `<c r="C1"><f>A1/B1</f><v>.5</v></c>`,
			match: true,
		},
		{
			name:  "genuine string result keeps t=str",
			in:    `<c r="B1" t="str"><f>IF(A1&gt;1,"big","small")</f><v>big</v></c>`,
			match: false,
		},
		{
			name:  "string result that starts with a digit keeps t=str",
			in:    `<c r="B1" t="str"><f>A1&amp;"x"</f><v>5x</v></c>`,
			match: false,
		},
		{
			name:  "error result keeps its type",
			in:    `<c r="B1" t="e"><f>1/0</f><v>#DIV/0!</v></c>`,
			match: false,
		},
		{
			name:  "formula without a cached value is untouched",
			in:    `<c r="C1" t="str"><f>SUM(A1:B1)</f></c>`,
			match: false,
		},
		{
			name:  "a plain shared-string cell is untouched",
			in:    `<c r="A1" t="s"><v>0</v></c>`,
			match: false,
		},
		{
			name:  "an inline string cell is untouched",
			in:    `<c r="A1" t="inlineStr"><is><t>hi</t></is></c>`,
			match: false,
		},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			got := string(reNumericStrFormula.ReplaceAll([]byte(tc.in), []byte("$1$2")))
			if tc.match {
				if got != tc.want {
					t.Errorf("got  %s\nwant %s", got, tc.want)
				}
				return
			}
			if got != tc.in {
				t.Errorf("must not rewrite:\n  in  %s\n  got %s", tc.in, got)
			}
		})
	}
}

func TestNeedsFormulaTypePatch(t *testing.T) {
	if !needsFormulaTypePatch([]byte(`<c r="C1" t="str"><f>SUM(A1:B1)</f><v>1</v></c>`)) {
		t.Error("should detect a numeric cached formula")
	}
	if needsFormulaTypePatch([]byte(`<c r="A1"><v>1</v></c>`)) {
		t.Error("should not fire on a plain numeric cell")
	}
}

func TestIsWorksheetPart(t *testing.T) {
	for _, ok := range []string{"xl/worksheets/sheet1.xml", "xl/worksheets/sheet12.xml"} {
		if !isWorksheetPart(ok) {
			t.Errorf("%s should be a worksheet part", ok)
		}
	}
	for _, no := range []string{
		"xl/workbook.xml",
		"xl/worksheets/_rels/sheet1.xml.rels",
		"xl/styles.xml",
		"xl/worksheets/",
	} {
		if isWorksheetPart(no) {
			t.Errorf("%s should not be treated as a worksheet part", no)
		}
	}
}

// Both container patches can apply to one save; the second must not undo the
// first, and the file must stay a valid workbook.
func TestFormulaTypePatchComposesWithAutoFilter(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetPrecalculateFormulas(true); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "both.xlsx")

	fillRows(t, w, "Worksheet", 1, 20)
	setFormula(t, w, "D21", "SUM(B1:B20)")
	if err := w.ApplyStyle("Worksheet", "D21", `{"numberFormat":{"formatCode":"#,##0"}}`); err != nil {
		t.Fatal(err)
	}
	if err := w.AutoFilter("Worksheet", "A1:C20"); err != nil {
		t.Fatal(err)
	}
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}

	// The filter may come from the container patch or, when a style forces a
	// degrade, from excelize's model writer — the element differs in
	// formatting between the two, so assert on the ref rather than the exact
	// serialisation.
	// The filter reaches the file by one of two routes: the container patch
	// (relative ref, `A1:C20`) on a purely streamed save, or excelize's model
	// writer (absolute, `$A$1:$C$20`) once a style forces a degrade. Both are
	// valid, so assert the element rather than one serialisation.
	assertSheetXMLContains(t, path, `autoFilter`)
	f := reopen(t, path)
	if displayed, _ := f.GetCellValue("Worksheet", "D21"); displayed != "210" {
		t.Errorf("displayed = %q, want 210 (both patches must apply)", displayed)
	}
	if v, _ := f.GetCellValue("Worksheet", "B20"); v != "20" {
		t.Errorf("data damaged by double patch: B20 = %q", v)
	}
}
