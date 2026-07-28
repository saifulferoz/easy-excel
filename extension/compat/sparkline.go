package compat

import (
	"encoding/json"
	"fmt"

	"github.com/xuri/excelize/v2"
)

// sparklineSpec is the JSON form of easy-excel's native sparkline API. Excel
// sparklines are tiny in-cell charts (one per location cell) driven by a data
// range; PhpSpreadsheet has no sparkline object model, so this is an
// easy-excel-native declarative feature mapped onto excelize.AddSparkline.
type sparklineSpec struct {
	Location []string `json:"location"`
	DataR    []string `json:"dataRange"`
	Type     string   `json:"type"` // line | column | win_loss
	Style    int      `json:"style"`
	Weight   float64  `json:"weight"`

	High     bool `json:"high"`
	Low      bool `json:"low"`
	First    bool `json:"first"`
	Last     bool `json:"last"`
	Negative bool `json:"negative"`
	Markers  bool `json:"markers"`
	Axis     bool `json:"axis"`
	Reverse  bool `json:"reverse"`

	SeriesColor   string `json:"seriesColor"`
	NegativeColor string `json:"negativeColor"`
	MarkersColor  string `json:"markersColor"`
	FirstColor    string `json:"firstColor"`
	LastColor     string `json:"lastColor"`
	HighColor     string `json:"highColor"`
	LowColor      string `json:"lowColor"`
}

// valid sparkline types (excelize also accepts these; we validate up front so a
// bad spec fails at queue time with a clear message rather than deep in save).
var sparklineTypes = map[string]bool{"line": true, "column": true, "win_loss": true}

// TranslateSparkline builds excelize.SparklineOptions from the JSON spec.
func TranslateSparkline(jsonSpec string) (*excelize.SparklineOptions, error) {
	var spec sparklineSpec
	if err := json.Unmarshal([]byte(jsonSpec), &spec); err != nil {
		return nil, fmt.Errorf("easy-excel: invalid sparkline spec: %w", err)
	}
	if len(spec.Location) == 0 {
		return nil, fmt.Errorf("easy-excel: sparkline needs at least one location cell")
	}
	if len(spec.DataR) == 0 {
		return nil, fmt.Errorf("easy-excel: sparkline needs at least one data range")
	}
	if len(spec.Location) != len(spec.DataR) {
		return nil, fmt.Errorf("easy-excel: sparkline location/dataRange count mismatch (%d vs %d)",
			len(spec.Location), len(spec.DataR))
	}
	if spec.Type != "" && !sparklineTypes[spec.Type] {
		return nil, fmt.Errorf("easy-excel: unsupported sparkline type %q (want line|column|win_loss)", spec.Type)
	}

	return &excelize.SparklineOptions{
		Location:      spec.Location,
		Range:         spec.DataR,
		Type:          spec.Type,
		Style:         spec.Style,
		Weight:        spec.Weight,
		High:          spec.High,
		Low:           spec.Low,
		First:         spec.First,
		Last:          spec.Last,
		Negative:      spec.Negative,
		Markers:       spec.Markers,
		Axis:          spec.Axis,
		Reverse:       spec.Reverse,
		SeriesColor:   spec.SeriesColor,
		NegativeColor: spec.NegativeColor,
		MarkersColor:  spec.MarkersColor,
		FirstColor:    spec.FirstColor,
		LastColor:     spec.LastColor,
		HightColor:    spec.HighColor, // excelize field is spelled "HightColor"
		LowColor:      spec.LowColor,
	}, nil
}
