package compat

import "testing"

func TestTranslateSparklineGroup(t *testing.T) {
	opts, err := TranslateSparklineGroup(`{"type":"stacked",
		"sparklines":[{"location":"G2","dataRange":"Sheet1!B2:F2"},{"location":"G3","dataRange":"Sheet1!B3:F3"}],
		"markers":true,"high":true,"negative":true,"xAxis":true,"rightToLeft":true,"colorSeries":"FF00B050"}`)
	if err != nil {
		t.Fatal(err)
	}
	if opts.Type != "win_loss" {
		t.Errorf("type = %q, want win_loss (OOXML stacked)", opts.Type)
	}
	if len(opts.Location) != 2 || opts.Location[1] != "G3" || opts.Range[1] != "Sheet1!B3:F3" {
		t.Errorf("locations/ranges = %v / %v", opts.Location, opts.Range)
	}
	if !opts.Markers || !opts.High || opts.Low || !opts.Negative || !opts.Axis || !opts.Reverse {
		t.Errorf("flags not mapped: %+v", opts)
	}
	if opts.SeriesColor != "00B050" {
		t.Errorf("series colour = %q, want alpha stripped", opts.SeriesColor)
	}
}

func TestTranslateSparklineGroupDefaults(t *testing.T) {
	opts, err := TranslateSparklineGroup(`{"sparklines":[{"location":"A1","dataRange":"B1:C1"}]}`)
	if err != nil {
		t.Fatal(err)
	}
	if opts.Type != "line" || opts.SeriesColor != "" {
		t.Errorf("defaults: type %q colour %q", opts.Type, opts.SeriesColor)
	}
}

func TestTranslateSparklineGroupRejects(t *testing.T) {
	for name, spec := range map[string]string{
		"bad json":      `{`,
		"bad type":      `{"type":"pie","sparklines":[{"location":"A1","dataRange":"B1:C1"}]}`,
		"empty group":   `{"type":"line","sparklines":[]}`,
		"bad location":  `{"sparklines":[{"location":"nope","dataRange":"B1:C1"}]}`,
		"no data range": `{"sparklines":[{"location":"A1","dataRange":""}]}`,
		"bad colour":    `{"sparklines":[{"location":"A1","dataRange":"B1:C1"}],"colorSeries":"red"}`,
	} {
		if _, err := TranslateSparklineGroup(spec); err == nil {
			t.Errorf("%s: expected an error", name)
		}
	}
}
