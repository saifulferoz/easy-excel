package core

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/xuri/excelize/v2"
)

func TestDocPropsRoundTrip(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetDocProps(`{"title":"ERP Report","subject":"Accounts",
		"creator":"easy-excel","keywords":"erp,report","category":"finance",
		"company":"BRAC"}`); err != nil {
		t.Fatal(err)
	}
	fillRows(t, w, "Worksheet", 1, 3)
	path := filepath.Join(t.TempDir(), "props.xlsx")
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	f := reopen(t, path)
	props, err := f.GetDocProps()
	if err != nil {
		t.Fatal(err)
	}
	if props.Title != "ERP Report" || props.Subject != "Accounts" || props.Creator != "easy-excel" {
		t.Errorf("doc props wrong: %+v", props)
	}
	app, err := f.GetAppProps()
	if err != nil {
		t.Fatal(err)
	}
	if app.Company != "BRAC" {
		t.Errorf("company wrong: %+v", app)
	}
}

func TestEncryptedSaveAndOpen(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "secret.xlsx")
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	fillRows(t, w, "Worksheet", 1, 5)
	if err := w.AutoFilter("Worksheet", "A1:C5"); err != nil { // must NOT use the patch path
		t.Fatal(err)
	}
	if err := w.SaveXlsx(path, "s3cret"); err != nil {
		t.Fatal(err)
	}
	w.Close()

	// wrong/no password must fail
	if _, err := excelize.OpenFile(path); err == nil {
		t.Fatal("encrypted file opened without password")
	}
	// our Open with the password works and data is intact
	w2, err := Open(path, "s3cret", testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w2.Close()
	v, err := w2.GetCell("Worksheet", "B3", GetFormatted)
	if err != nil {
		t.Fatal(err)
	}
	if v != "3" {
		t.Errorf("B3 = %v, want 3", v)
	}
}

func TestEncryptedSaveAppliesFilterInModel(t *testing.T) {
	// encryption disables the container patch; the filter must still exist
	dir := t.TempDir()
	path := filepath.Join(dir, "filtered-secret.xlsx")
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	fillRows(t, w, "Worksheet", 1, 10)
	if err := w.AutoFilter("Worksheet", "A1:C10"); err != nil {
		t.Fatal(err)
	}
	if err := w.SaveXlsx(path, "pw"); err != nil {
		t.Fatal(err)
	}
	f, err := excelize.OpenFile(path, excelize.Options{Password: "pw"})
	if err != nil {
		t.Fatal(err)
	}
	defer f.Close()
	// the encrypted container cannot be zip-grepped; re-save decrypted and
	// check the worksheet XML carries the filter applied via the model
	plain := filepath.Join(dir, "plain.xlsx")
	if err := f.SaveAs(plain, excelize.Options{Password: ""}); err != nil {
		t.Fatal(err)
	}
	assertSheetXMLContains(t, plain, "<autoFilter ref=")
}

func TestUnmergeCells(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "unmerged.xlsx")

	// queued merge dropped before it is ever applied
	if err := w.MergeCells("Worksheet", "A1:C1"); err != nil {
		t.Fatal(err)
	}
	if err := w.UnmergeCells("Worksheet", "A1:C1"); err != nil {
		t.Fatal(err)
	}
	// merge applied through the StreamWriter, unmerged at save
	fillRows(t, w, "Worksheet", 1, 5)
	if err := w.MergeCells("Worksheet", "A4:B5"); err != nil {
		t.Fatal(err)
	}
	if err := w.MergeCells("Worksheet", "A2:C2"); err != nil {
		t.Fatal(err)
	}
	if err := w.UnmergeCells("Worksheet", "A4:B5"); err != nil {
		t.Fatal(err)
	}
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	f := reopen(t, path)
	merged, err := f.GetMergeCells("Worksheet")
	if err != nil {
		t.Fatal(err)
	}
	refs := make([]string, 0, len(merged))
	for _, m := range merged {
		refs = append(refs, m.GetStartAxis()+":"+m.GetEndAxis())
	}
	if len(refs) != 1 || refs[0] != "A2:C2" {
		t.Errorf("merges after unmerge = %v, want [A2:C2]", refs)
	}
}

func TestMergesGetter(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.MergeCells("Worksheet", "A1:B2"); err != nil {
		t.Fatal(err)
	}
	fillRows(t, w, "Worksheet", 1, 3)
	refs, err := w.Merges("Worksheet")
	if err != nil {
		t.Fatal(err)
	}
	if len(refs) != 1 || refs[0] != "A1:B2" {
		t.Errorf("Merges() = %v", refs)
	}
}

func TestPrintTitlesViaDefinedName(t *testing.T) {
	// the PHP shim sends print titles as the reserved _xlnm defined name;
	// verify excelize accepts and persists it
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	fillRows(t, w, "Worksheet", 1, 10)
	if err := w.SetDefinedName("_xlnm.Print_Titles", "Worksheet!$6:$7", "Worksheet"); err != nil {
		t.Fatal(err)
	}
	if err := w.SetDefinedName("_xlnm.Print_Area", "Worksheet!$A$1:$C$10", "Worksheet"); err != nil {
		t.Fatal(err)
	}
	path := filepath.Join(t.TempDir(), "titles.xlsx")
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	f := reopen(t, path)
	foundTitles, foundArea := false, false
	for _, dn := range f.GetDefinedName() {
		if dn.Name == "_xlnm.Print_Titles" && strings.Contains(dn.RefersTo, "$6:$7") {
			foundTitles = true
		}
		if dn.Name == "_xlnm.Print_Area" && strings.Contains(dn.RefersTo, "$A$1:$C$10") {
			foundArea = true
		}
	}
	if !foundTitles || !foundArea {
		t.Errorf("print names missing: titles=%v area=%v (%+v)", foundTitles, foundArea, f.GetDefinedName())
	}
}
