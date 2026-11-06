package core

import (
	"archive/zip"
	"io"
	"path/filepath"
	"strings"
	"testing"
)

func TestAddSparklineGroupOnStreamedSheet(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "sparklines.xlsx")

	fillRows(t, w, "Worksheet", 1, 5)
	if err := w.AddSparklineGroup("Worksheet", `{"type":"column",
		"sparklines":[{"location":"G1","dataRange":"Worksheet!A1:B1"},{"location":"G2","dataRange":"Worksheet!A2:B2"}],
		"high":true,"colorSeries":"FF00B050"}`); err != nil {
		t.Fatal(err)
	}
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}

	assertSheetXMLContains(t, path, `type="column"`)
	assertSheetXMLContains(t, path, `<xm:f>Worksheet!A2:B2</xm:f><xm:sqref>G2</xm:sqref>`)
	assertSheetXMLContains(t, path, `high="true"`)
	assertSheetXMLContains(t, path, `rgb="FF00B050"`)

	f := reopen(t, path)
	if v, _ := f.GetCellValue("Worksheet", "B5"); v != "5" {
		t.Errorf("B5 = %q, want 5 — data must survive the sparkline flush", v)
	}
}

// Two groups on one sheet land in the same x14:sparklineGroups container
// rather than one replacing the other.
func TestAddSparklineGroupAppends(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "two-groups.xlsx")

	fillRows(t, w, "Worksheet", 1, 3)
	for _, spec := range []string{
		`{"type":"line","sparklines":[{"location":"G1","dataRange":"Worksheet!A1:B1"}]}`,
		`{"type":"stacked","sparklines":[{"location":"G2","dataRange":"Worksheet!A2:B2"}]}`,
	} {
		if err := w.AddSparklineGroup("Worksheet", spec); err != nil {
			t.Fatal(err)
		}
	}
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}

	xml := worksheetXML(t, path, "xl/worksheets/sheet1.xml")
	if n := strings.Count(xml, "<x14:sparklineGroup "); n != 2 {
		t.Errorf("sparkline groups = %d, want 2", n)
	}
	if strings.Count(xml, "<x14:sparklineGroups") != 1 {
		t.Error("groups must share one x14:sparklineGroups container")
	}
	assertSheetXMLContains(t, path, `type="stacked"`)
}

func TestAddSparklineGroupRejectsBadSpec(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()

	if err := w.AddSparklineGroup("Worksheet", `{"type":"pie","sparklines":[{"location":"A1","dataRange":"B1:C1"}]}`); err == nil {
		t.Error("unknown type must fail at the call site")
	}
	if err := w.AddSparklineGroup("Nope", `{"sparklines":[{"location":"A1","dataRange":"B1:C1"}]}`); err == nil {
		t.Error("unknown sheet must fail")
	}
}

func worksheetXML(t *testing.T, path, part string) string {
	t.Helper()
	zr, err := zip.OpenReader(path)
	if err != nil {
		t.Fatal(err)
	}
	defer zr.Close()
	for _, zf := range zr.File {
		if zf.Name != part {
			continue
		}
		r, err := zf.Open()
		if err != nil {
			t.Fatal(err)
		}
		defer r.Close()
		data, err := io.ReadAll(r)
		if err != nil {
			t.Fatal(err)
		}
		return string(data)
	}
	t.Fatalf("%s missing from %s", part, path)
	return ""
}
