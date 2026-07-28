package compat

import "testing"

func TestTranslateSparklineFullSpec(t *testing.T) {
	opts, err := TranslateSparkline(`{
		"type": "column",
		"location": ["G2", "G3"],
		"dataRange": ["A2:F2", "A3:F3"],
		"style": 13,
		"high": true, "low": true, "markers": true,
		"seriesColor": "FF0000", "markersColor": "00FF00"
	}`)
	if err != nil {
		t.Fatal(err)
	}
	if opts.Type != "column" {
		t.Errorf("type: got %q", opts.Type)
	}
	if len(opts.Location) != 2 || opts.Location[1] != "G3" {
		t.Errorf("location: %+v", opts.Location)
	}
	if len(opts.Range) != 2 || opts.Range[0] != "A2:F2" {
		t.Errorf("range: %+v", opts.Range)
	}
	if opts.Style != 13 || !opts.High || !opts.Low || !opts.Markers {
		t.Errorf("flags/style wrong: %+v", opts)
	}
	if opts.SeriesColor != "FF0000" || opts.MarkersColor != "00FF00" {
		t.Errorf("colors wrong: %+v", opts)
	}
}

func TestTranslateSparklineDefaults(t *testing.T) {
	opts, err := TranslateSparkline(`{"location": ["B1"], "dataRange": ["C1:H1"]}`)
	if err != nil {
		t.Fatal(err)
	}
	if opts.Type != "" { // excelize defaults empty -> "line"
		t.Errorf("expected empty type default, got %q", opts.Type)
	}
	if opts.High || opts.Low || opts.Markers {
		t.Errorf("toggles should default false: %+v", opts)
	}
}

func TestTranslateSparklineErrors(t *testing.T) {
	cases := map[string]string{
		"no location":    `{"dataRange": ["A1:F1"]}`,
		"no dataRange":   `{"location": ["G1"]}`,
		"count mismatch": `{"location": ["G1", "G2"], "dataRange": ["A1:F1"]}`,
		"bad type":       `{"location": ["G1"], "dataRange": ["A1:F1"], "type": "pie"}`,
		"invalid json":   `{not json`,
	}
	for name, spec := range cases {
		if _, err := TranslateSparkline(spec); err == nil {
			t.Errorf("%s: expected error, got nil", name)
		}
	}
}
