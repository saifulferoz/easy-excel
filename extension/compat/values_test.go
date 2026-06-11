package compat

import "testing"

func TestBindScalars(t *testing.T) {
	cases := []struct {
		in   any
		want Cell
	}{
		{nil, Cell{Kind: Skip}},
		{true, Cell{Kind: Boolean, Bool: true}},
		{int64(42), Cell{Kind: Num, Num: 42}},
		{3.14, Cell{Kind: Num, Num: 3.14}},
		{"hello", Cell{Kind: Str, Str: "hello"}},
		// DefaultValueBinder: numeric strings become numbers…
		{"123", Cell{Kind: Num, Num: 123}},
		{"-1.5", Cell{Kind: Num, Num: -1.5}},
		{"+2", Cell{Kind: Num, Num: 2}},
		{"1e3", Cell{Kind: Num, Num: 1000}},
		{"0.5", Cell{Kind: Num, Num: 0.5}},
		// …except leading-zero integers (phone numbers, zip codes)
		{"01513", Cell{Kind: Str, Str: "01513"}},
		{"0", Cell{Kind: Num, Num: 0}},
		// formulas
		{"=SUM(A1:A3)", Cell{Kind: Formula, Str: "SUM(A1:A3)"}},
		{"=", Cell{Kind: Str, Str: "="}}, // bare '=' is a string, like PhpSpreadsheet
		// not numbers
		{"12 34", Cell{Kind: Str, Str: "12 34"}},
		{"1,234", Cell{Kind: Str, Str: "1,234"}},
		{"", Cell{Kind: Str, Str: ""}},
	}
	for _, c := range cases {
		got, err := Decode(c.in)
		if err != nil {
			t.Fatalf("Decode(%v): %v", c.in, err)
		}
		if got != c.want {
			t.Errorf("Decode(%#v) = %+v, want %+v", c.in, got, c.want)
		}
	}
}

func TestExplicitMarkers(t *testing.T) {
	// TYPE_STRING must defeat formula/number auto-detection
	got, err := Decode([]any{MarkString, "=SUM(A1)"})
	if err != nil || got != (Cell{Kind: Str, Str: "=SUM(A1)"}) {
		t.Fatalf("explicit string: %+v, %v", got, err)
	}
	got, _ = Decode([]any{MarkString, "0123"})
	if got.Kind != Str || got.Str != "0123" {
		t.Fatalf("explicit string number: %+v", got)
	}
	got, err = Decode([]any{MarkNumeric, "19.5"})
	if err != nil || got != (Cell{Kind: Num, Num: 19.5}) {
		t.Fatalf("explicit numeric: %+v, %v", got, err)
	}
	got, err = Decode([]any{MarkFormula, "=A1+A2"})
	if err != nil || got != (Cell{Kind: Formula, Str: "A1+A2"}) {
		t.Fatalf("explicit formula strips '=': %+v, %v", got, err)
	}
	got, err = Decode([]any{MarkBool, int64(1)})
	if err != nil || got != (Cell{Kind: Boolean, Bool: true}) {
		t.Fatalf("explicit bool: %+v, %v", got, err)
	}
}

func TestExplicitErrors(t *testing.T) {
	if _, err := Decode([]any{"???", "x"}); err == nil {
		t.Fatal("unknown marker must error")
	}
	if _, err := Decode([]any{1, 2, 3}); err == nil {
		t.Fatal("arbitrary array cell must error")
	}
	if _, err := Decode([]any{MarkNumeric, "abc"}); err == nil {
		t.Fatal("non-numeric explicit numeric must error")
	}
	if _, err := Decode(map[string]any{"a": 1}); err == nil {
		t.Fatal("map cell must error")
	}
}
