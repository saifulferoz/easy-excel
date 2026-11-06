package compat

import (
	"encoding/json"
	"fmt"
	"strings"

	"github.com/xuri/excelize/v2"
)

// sparklineGroupSpec is the JSON form of PhpSpreadsheet 5.10's
// Worksheet\Sparkline\SparklineGroup sent by the shim. Only the properties
// excelize's AddSparkline renders are carried; the rest of the group
// (line weight, empty-cell mode, axis scaling, non-series colours) has no
// excelize option and stays on the PHP side (COMPAT.md divergence).
type sparklineGroupSpec struct {
	Type        string          `json:"type"` // OOXML value: line, column, stacked
	Sparklines  []sparklineSpec `json:"sparklines"`
	Markers     bool            `json:"markers"`
	High        bool            `json:"high"`
	Low         bool            `json:"low"`
	First       bool            `json:"first"`
	Last        bool            `json:"last"`
	Negative    bool            `json:"negative"`
	XAxis       bool            `json:"xAxis"`
	RightToLeft bool            `json:"rightToLeft"`
	ColorSeries string          `json:"colorSeries"` // ARGB or RGB hex; "" keeps the preset
}

type sparklineSpec struct {
	Location  string `json:"location"`
	DataRange string `json:"dataRange"`
}

// OOXML sparkline types → excelize's Type names (it maps them back).
var sparklineTypes = map[string]string{
	"":        "line",
	"line":    "line",
	"column":  "column",
	"stacked": "win_loss",
}

// TranslateSparklineGroup maps a sparkline group spec onto excelize options.
func TranslateSparklineGroup(jsonSpec string) (*excelize.SparklineOptions, error) {
	var spec sparklineGroupSpec
	if err := json.Unmarshal([]byte(jsonSpec), &spec); err != nil {
		return nil, fmt.Errorf("easy-excel: invalid sparkline spec: %w", err)
	}
	typ, ok := sparklineTypes[spec.Type]
	if !ok {
		return nil, fmt.Errorf("easy-excel: unsupported sparkline type %q", spec.Type)
	}
	if len(spec.Sparklines) == 0 {
		return nil, fmt.Errorf("easy-excel: sparkline group has no sparklines")
	}
	opts := &excelize.SparklineOptions{
		Type:     typ,
		Markers:  spec.Markers,
		High:     spec.High,
		Low:      spec.Low,
		First:    spec.First,
		Last:     spec.Last,
		Negative: spec.Negative,
		Axis:     spec.XAxis,
		Reverse:  spec.RightToLeft,
	}
	for i, sp := range spec.Sparklines {
		if _, _, err := excelize.CellNameToCoordinates(sp.Location); err != nil {
			return nil, fmt.Errorf("easy-excel: sparkline %d location %q: %w", i, sp.Location, err)
		}
		if sp.DataRange == "" {
			return nil, fmt.Errorf("easy-excel: sparkline %d at %s has no data range", i, sp.Location)
		}
		opts.Location = append(opts.Location, sp.Location)
		opts.Range = append(opts.Range, sp.DataRange)
	}
	if spec.ColorSeries != "" {
		rgb, err := sparklineRGB(spec.ColorSeries)
		if err != nil {
			return nil, err
		}
		opts.SeriesColor = rgb
	}
	return opts, nil
}

// sparklineRGB reduces PhpSpreadsheet's ARGB colour to the RRGGBB excelize
// expects (it re-adds an opaque alpha itself).
func sparklineRGB(color string) (string, error) {
	c := strings.TrimPrefix(color, "#")
	if len(c) == 8 {
		c = c[2:]
	}
	if len(c) != 6 || strings.Trim(strings.ToUpper(c), "0123456789ABCDEF") != "" {
		return "", fmt.Errorf("easy-excel: invalid sparkline colour %q", color)
	}
	return strings.ToUpper(c), nil
}
