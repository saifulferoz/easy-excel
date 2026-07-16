package core

import (
	"path/filepath"
	"testing"

	"github.com/xuri/excelize/v2"
)

// PhpSpreadsheet writes to any filename; excelize's SaveAs does not. The
// shim's wrapped-target staging and consumer temp files rely on parity.
func TestSaveXlsxExtensionlessPath(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "report") // no extension

	fillRows(t, w, "Worksheet", 1, 10)
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatalf("extension-less save failed: %v", err)
	}

	f, err := excelize.OpenFile(path)
	if err != nil {
		t.Fatalf("reopen: %v", err)
	}
	defer f.Close()
	if v, _ := f.GetCellValue("Worksheet", "B10"); v != "10" {
		t.Errorf("B10 = %q, want 10", v)
	}
	// nothing staged left behind
	if _, err := excelize.OpenFile(path + ".eexcel.xlsx"); err == nil {
		t.Error("staging file was not renamed away")
	}
}

func TestSaveXlsxExtensionlessPathEncrypted(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "secret") // no extension

	fillRows(t, w, "Worksheet", 1, 3)
	if err := w.SaveXlsx(path, "pw"); err != nil {
		t.Fatalf("encrypted extension-less save failed: %v", err)
	}
	f, err := excelize.OpenFile(path, excelize.Options{Password: "pw"})
	if err != nil {
		t.Fatalf("reopen with password: %v", err)
	}
	defer f.Close()
	if v, _ := f.GetCellValue("Worksheet", "B3"); v != "3" {
		t.Errorf("B3 = %q, want 3", v)
	}
}
