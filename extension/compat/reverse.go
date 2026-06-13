package compat

import (
	"github.com/xuri/excelize/v2"
)

// Reverse translation (wave 4.2): excelize styles back into the
// PhpSpreadsheet spec shape, so getStyle() can reflect loaded files and
// duplicateStyle() can copy them. Only what TranslateStyle can produce is
// reversed; exotic stylesheet content comes back best-effort.

var borderStyleNames = invertIntMap(borderStyles)
var fillPatternNames = invertIntMap(fillPatterns)

func invertIntMap(m map[string]int) map[int]string {
	out := make(map[int]string, len(m))
	for k, v := range m {
		out[v] = k
	}
	return out
}

// builtinNumFmts maps the OOXML built-in numFmt ids PhpSpreadsheet users
// meet in practice (ECMA-376 §18.8.30 subset).
var builtinNumFmts = map[int]string{
	0: "General", 1: "0", 2: "0.00", 3: "#,##0", 4: "#,##0.00",
	9: "0%", 10: "0.00%", 11: "0.00E+00", 12: "# ?/?", 13: "# ??/??",
	14: "m/d/yy", 15: "d-mmm-yy", 16: "d-mmm", 17: "mmm-yy",
	18: "h:mm AM/PM", 19: "h:mm:ss AM/PM", 20: "h:mm", 21: "h:mm:ss",
	22: "m/d/yy h:mm", 37: "#,##0 ;(#,##0)", 38: "#,##0 ;[Red](#,##0)",
	39: "#,##0.00;(#,##0.00)", 40: "#,##0.00;[Red](#,##0.00)",
	45: "mm:ss", 46: "[h]:mm:ss", 47: "mmss.0", 48: "##0.0E+0", 49: "@",
}

// ReverseStyle converts an excelize style into the applyFromArray spec shape.
func ReverseStyle(s *excelize.Style) StyleSpec {
	spec := StyleSpec{}
	if s == nil {
		return spec
	}
	if f := s.Font; f != nil {
		font := map[string]any{}
		if f.Bold {
			font["bold"] = true
		}
		if f.Italic {
			font["italic"] = true
		}
		if f.Strike {
			font["strikethrough"] = true
		}
		if f.Underline != "" {
			font["underline"] = f.Underline
		}
		if f.Family != "" {
			font["name"] = f.Family
		}
		if f.Size > 0 {
			font["size"] = f.Size
		}
		if f.Color != "" {
			font["color"] = map[string]any{"rgb": stripAlpha(f.Color)}
		}
		if len(font) > 0 {
			spec["font"] = font
		}
	}
	if s.Fill.Type == "gradient" && len(s.Fill.Color) > 0 {
		fill := map[string]any{
			"fillType":   "linear",
			"rotation":   shadingRotation(s.Fill.Shading),
			"startColor": map[string]any{"rgb": stripAlpha(s.Fill.Color[0])},
		}
		if s.Fill.Shading == 16 {
			fill["fillType"] = "path"
		}
		if len(s.Fill.Color) > 1 {
			fill["endColor"] = map[string]any{"rgb": stripAlpha(s.Fill.Color[1])}
		}
		spec["fill"] = fill
	} else if s.Fill.Pattern > 0 {
		fill := map[string]any{"fillType": fillPatternNames[s.Fill.Pattern]}
		if len(s.Fill.Color) > 0 {
			fill["startColor"] = map[string]any{"rgb": stripAlpha(s.Fill.Color[0])}
		}
		spec["fill"] = fill
	}
	if len(s.Border) > 0 {
		borders := map[string]any{}
		diagonal := 0
		for _, b := range s.Border {
			side := map[string]any{"borderStyle": borderStyleNames[b.Style]}
			if b.Color != "" {
				side["color"] = map[string]any{"rgb": stripAlpha(b.Color)}
			}
			switch b.Type {
			case "diagonalUp":
				diagonal |= 1
				borders["diagonal"] = side
			case "diagonalDown":
				diagonal |= 2
				borders["diagonal"] = side
			default:
				borders[b.Type] = side
			}
		}
		if diagonal != 0 {
			borders["diagonalDirection"] = float64(diagonal)
		}
		if len(borders) > 0 {
			spec["borders"] = borders
		}
	}
	if a := s.Alignment; a != nil {
		align := map[string]any{}
		if a.Horizontal != "" {
			align["horizontal"] = a.Horizontal
		}
		if a.Vertical != "" {
			align["vertical"] = a.Vertical
		}
		if a.WrapText {
			align["wrapText"] = true
		}
		if a.ShrinkToFit {
			align["shrinkToFit"] = true
		}
		if a.TextRotation != 0 {
			align["textRotation"] = float64(a.TextRotation)
		}
		if a.Indent != 0 {
			align["indent"] = float64(a.Indent)
		}
		if len(align) > 0 {
			spec["alignment"] = align
		}
	}
	if p := s.Protection; p != nil {
		prot := map[string]any{}
		if !p.Locked {
			prot["locked"] = "unprotected"
		}
		if p.Hidden {
			prot["hidden"] = true
		}
		if len(prot) > 0 {
			spec["protection"] = prot
		}
	}
	switch {
	case s.CustomNumFmt != nil && *s.CustomNumFmt != "":
		spec["numberFormat"] = map[string]any{"formatCode": *s.CustomNumFmt}
	case s.NumFmt != 0:
		if code, ok := builtinNumFmts[s.NumFmt]; ok {
			spec["numberFormat"] = map[string]any{"formatCode": code}
		}
	}
	return spec
}

// shadingRotation is the inverse of gradientShading's bucket centers.
func shadingRotation(shading int) float64 {
	switch {
	case shading >= 3 && shading <= 5:
		return 90
	case shading >= 6 && shading <= 8:
		return 135
	case shading >= 9 && shading <= 11:
		return 45
	default:
		return 0
	}
}

// ReverseValidation converts an excelize data validation into the shim's
// spec shape plus its range.
func ReverseValidation(dv *excelize.DataValidation) (ref string, spec map[string]any) {
	spec = map[string]any{
		"type":             dv.Type,
		"operator":         dv.Operator,
		"formula1":         dv.Formula1,
		"formula2":         dv.Formula2,
		"allowBlank":       dv.AllowBlank,
		"showDropDown":     dv.ShowDropDown,
		"showInputMessage": dv.ShowInputMessage,
		"showErrorMessage": dv.ShowErrorMessage,
	}
	deref := func(p *string) string {
		if p == nil {
			return ""
		}
		return *p
	}
	spec["errorStyle"] = deref(dv.ErrorStyle)
	spec["errorTitle"] = deref(dv.ErrorTitle)
	spec["error"] = deref(dv.Error)
	spec["promptTitle"] = deref(dv.PromptTitle)
	spec["prompt"] = deref(dv.Prompt)
	return dv.Sqref, spec
}

var conditionalOperators = invertStringMap(conditionalCriteria)

func invertStringMap(m map[string]string) map[string]string {
	out := make(map[string]string, len(m))
	for k, v := range m {
		out[v] = k
	}
	// excelize reads criteria back in long form, not the symbols it accepts
	for long, op := range map[string]string{
		"equal to":                 "equal",
		"not equal to":             "notEqual",
		"greater than":             "greaterThan",
		"greater than or equal to": "greaterThanOrEqual",
		"less than":                "lessThan",
		"less than or equal to":    "lessThanOrEqual",
	} {
		out[long] = op
	}
	return out
}

// ReverseConditional converts excelize conditional-format options back into
// the shim's rule shape (style is returned as a style spec when resolvable).
func ReverseConditional(opt excelize.ConditionalFormatOptions, style *excelize.Style) map[string]any {
	rule := map[string]any{"stopIfTrue": opt.StopIfTrue}
	switch opt.Type {
	case "cell", "text":
		rule["type"] = "cellIs"
		if opt.Type == "text" {
			rule["type"] = "containsText"
		}
		if op, ok := conditionalOperators[opt.Criteria]; ok {
			rule["operator"] = op
		}
		if opt.Value != "" {
			rule["conditions"] = []any{opt.Value}
		}
	case "formula":
		rule["type"] = "expression"
		rule["conditions"] = []any{opt.Criteria}
	case "2_color_scale", "3_color_scale":
		rule["type"] = "colorScale"
		scale := map[string]any{"minColor": opt.MinColor, "maxColor": opt.MaxColor}
		if opt.MidColor != "" {
			scale["midColor"] = opt.MidColor
		}
		rule["colorScale"] = scale
	case "data_bar":
		rule["type"] = "dataBar"
		rule["dataBar"] = map[string]any{"color": opt.BarColor}
	default:
		rule["type"] = opt.Type
	}
	if style != nil {
		if spec := ReverseStyle(style); len(spec) > 0 {
			rule["style"] = map[string]any(spec)
		}
	}
	return rule
}
