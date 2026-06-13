package core

import (
	"encoding/json"
	"path/filepath"
	"testing"
)

func TestStyleReadBack(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.ApplyStyle("Worksheet", "B2",
		`{"font":{"bold":true,"size":14},"numberFormat":{"formatCode":"0.00%"},"alignment":{"horizontal":"center"}}`); err != nil {
		t.Fatal(err)
	}
	fillRows(t, w, "Worksheet", 1, 3)
	raw, err := w.GetStyleSpec("Worksheet", "B2")
	if err != nil {
		t.Fatal(err)
	}
	var spec map[string]map[string]any
	if err := json.Unmarshal([]byte(raw), &spec); err != nil {
		t.Fatal(err)
	}
	if spec["font"]["bold"] != true || spec["font"]["size"] != 14.0 {
		t.Errorf("font read-back wrong: %v", spec["font"])
	}
	if spec["numberFormat"]["formatCode"] != "0.00%" {
		t.Errorf("format read-back wrong: %v", spec["numberFormat"])
	}
	if spec["alignment"]["horizontal"] != "center" {
		t.Errorf("alignment read-back wrong: %v", spec["alignment"])
	}
}

func TestStyleReadBackFromLoadedFile(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "styled.xlsx")
	w, _ := New(testEnv())
	_ = w.ApplyStyle("Worksheet", "A1", `{"font":{"italic":true},"fill":{"fillType":"solid","startColor":{"rgb":"FFFF00"}}}`)
	fillRows(t, w, "Worksheet", 1, 2)
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	w.Close()

	w2, err := Open(path, "", testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w2.Close()
	raw, err := w2.GetStyleSpec("Worksheet", "A1")
	if err != nil {
		t.Fatal(err)
	}
	var spec map[string]map[string]any
	_ = json.Unmarshal([]byte(raw), &spec)
	if spec["font"]["italic"] != true {
		t.Errorf("loaded-file italic missing: %v", spec)
	}
	if spec["fill"]["fillType"] != "solid" {
		t.Errorf("loaded-file fill missing: %v", spec)
	}
}

func TestValidationAndConditionalReadBack(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	fillRows(t, w, "Worksheet", 1, 9)
	if err := w.SetDataValidation("Worksheet", "C2:C9",
		`{"type":"list","formula1":"a,b","allowBlank":true,"showErrorMessage":true}`); err != nil {
		t.Fatal(err)
	}
	if err := w.SetConditionalFormat("Worksheet", "B2:B9",
		`[{"type":"cellIs","operator":"greaterThan","conditions":["5"],"style":{"font":{"bold":true}}}]`); err != nil {
		t.Fatal(err)
	}

	vraw, err := w.ValidationsJSON("Worksheet")
	if err != nil {
		t.Fatal(err)
	}
	var validations []struct {
		Sqref string         `json:"sqref"`
		Spec  map[string]any `json:"spec"`
	}
	_ = json.Unmarshal([]byte(vraw), &validations)
	if len(validations) != 1 || validations[0].Sqref != "C2:C9" || validations[0].Spec["type"] != "list" {
		t.Errorf("validation read-back wrong: %s", vraw)
	}

	craw, err := w.ConditionalsJSON("Worksheet")
	if err != nil {
		t.Fatal(err)
	}
	var conditionals map[string][]map[string]any
	_ = json.Unmarshal([]byte(craw), &conditionals)
	rules := conditionals["B2:B9"]
	if len(rules) != 1 || rules[0]["type"] != "cellIs" || rules[0]["operator"] != "greaterThan" {
		t.Errorf("conditional read-back wrong: %s", craw)
	}
	if style, ok := rules[0]["style"].(map[string]any); !ok || style["font"].(map[string]any)["bold"] != true {
		t.Errorf("conditional style read-back wrong: %s", craw)
	}
}

func TestDefinedNamesReadBack(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	if err := w.SetDefinedName("data", "Worksheet!$A$1:$B$2", ""); err != nil {
		t.Fatal(err)
	}
	raw, err := w.DefinedNamesJSON()
	if err != nil {
		t.Fatal(err)
	}
	var names []map[string]string
	_ = json.Unmarshal([]byte(raw), &names)
	found := false
	for _, n := range names {
		if n["name"] == "data" && n["refersTo"] == "Worksheet!$A$1:$B$2" {
			found = true
		}
	}
	if !found {
		t.Errorf("defined names read-back wrong: %s", raw)
	}
}

func TestDefaultStyleStreamsAndLayers(t *testing.T) {
	w, err := New(testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w.Close()
	path := filepath.Join(t.TempDir(), "default.xlsx")

	if err := w.SetDefaultStyle(`{"font":{"name":"Arial","size":9}}`); err != nil {
		t.Fatal(err)
	}
	if err := w.ApplyStyle("Worksheet", "A1", `{"font":{"bold":true}}`); err != nil {
		t.Fatal(err)
	}
	fillRows(t, w, "Worksheet", 1, 5)
	if err := w.SaveXlsx(path, ""); err != nil {
		t.Fatal(err)
	}
	if w.Degraded() {
		t.Fatal("default style should ride the StreamWriter column style")
	}
	f := reopen(t, path)
	// styled cell: default layered under the explicit bold
	s := cellStyle(t, f, "Worksheet", "A1")
	if s.Font == nil || !s.Font.Bold || s.Font.Family != "Arial" || s.Font.Size != 9 {
		t.Errorf("A1 should be bold Arial 9: %+v", s.Font)
	}
	// untouched cell: covered by the full-width column style
	if width, err := f.GetColWidth("Worksheet", "B"); err != nil || width <= 0 {
		t.Fatalf("sheet unreadable: %v", err)
	}
	id, err := f.GetColStyle("Worksheet", "ZZ")
	if err != nil {
		t.Fatal(err)
	}
	colStyle, err := f.GetStyle(id)
	if err != nil {
		t.Fatal(err)
	}
	if colStyle.Font == nil || colStyle.Font.Family != "Arial" || colStyle.Font.Size != 9 {
		t.Errorf("column default style wrong: %+v", colStyle.Font)
	}
}

func TestDefaultStyleOnRandomWorkbook(t *testing.T) {
	dir := t.TempDir()
	src := filepath.Join(dir, "src.xlsx")
	w, _ := New(testEnv())
	fillRows(t, w, "Worksheet", 1, 2)
	if err := w.SaveXlsx(src, ""); err != nil {
		t.Fatal(err)
	}
	w.Close()

	w2, err := Open(src, "", testEnv())
	if err != nil {
		t.Fatal(err)
	}
	defer w2.Close()
	if err := w2.SetDefaultStyle(`{"font":{"size":9}}`); err != nil {
		t.Fatal(err)
	}
	out := filepath.Join(dir, "out.xlsx")
	if err := w2.SaveXlsx(out, ""); err != nil {
		t.Fatal(err)
	}
	f := reopen(t, out)
	id, err := f.GetColStyle("Worksheet", "M")
	if err != nil {
		t.Fatal(err)
	}
	style, err := f.GetStyle(id)
	if err != nil {
		t.Fatal(err)
	}
	if style.Font == nil || style.Font.Size != 9 {
		t.Errorf("default style on loaded file wrong: %+v", style.Font)
	}
}
