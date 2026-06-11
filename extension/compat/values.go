// Package compat reproduces PhpSpreadsheet semantics on the Go side
// (PLAN.md §4 compat layer). Divergences are documented in COMPAT.md.
package compat

import (
	"fmt"
	"regexp"
	"strconv"
)

// Kind classifies a bound cell value.
type Kind byte

const (
	Skip    Kind = iota // PHP null: leave the cell untouched
	Str                 // inline/shared string
	Num                 // float64
	Boolean             // bool
	Formula             // formula source without the leading '='
)

// Cell is a bound value ready for the excelize adapter.
type Cell struct {
	Kind Kind
	Str  string
	Num  float64
	Bool bool
}

// Explicit type markers used by the PHP shim to encode
// setCellValueExplicit() cells inside batched rows: a cell may be a
// 2-element packed array [marker, value]. Markers start with "=" because no
// PhpSpreadsheet DataType constant does, and arrays are never valid cell
// values, so the encoding cannot collide with user data.
const (
	MarkString  = "=s"
	MarkNumeric = "=n"
	MarkBool    = "=b"
	MarkFormula = "=f"
)

// PhpSpreadsheet DefaultValueBinder: strings matching this pattern are
// stored as numbers, except integers with a leading zero (e.g. "01513").
var numericPattern = regexp.MustCompile(`^[+-]?(\d+\.?\d*|\d*\.?\d+)([eE][+-]?\d+)?$`)

// Decode converts one cell slot received from PHP (a scalar, or an explicit
// [marker, value] pair) into a bound Cell, mirroring DefaultValueBinder.
func Decode(v any) (Cell, error) {
	if pair, ok := v.([]any); ok {
		return decodeExplicit(pair)
	}
	return bindScalar(v)
}

func bindScalar(v any) (Cell, error) {
	switch t := v.(type) {
	case nil:
		return Cell{Kind: Skip}, nil
	case bool:
		return Cell{Kind: Boolean, Bool: t}, nil
	case int64:
		return Cell{Kind: Num, Num: float64(t)}, nil
	case int:
		return Cell{Kind: Num, Num: float64(t)}, nil
	case float64:
		return Cell{Kind: Num, Num: t}, nil
	case string:
		return bindString(t), nil
	default:
		return Cell{}, fmt.Errorf("easy-excel: unsupported cell value of type %T", v)
	}
}

// bindString applies DefaultValueBinder::dataTypeForValue() rules.
func bindString(s string) Cell {
	if len(s) > 1 && s[0] == '=' {
		return Cell{Kind: Formula, Str: s[1:]}
	}
	if isNumericString(s) {
		n, err := strconv.ParseFloat(s, 64)
		if err == nil {
			return Cell{Kind: Num, Num: n}
		}
	}
	return Cell{Kind: Str, Str: s}
}

func isNumericString(s string) bool {
	if !numericPattern.MatchString(s) {
		return false
	}
	// "0123" stays a string (leading zero, not a decimal like "0.5")
	digits := s
	if digits[0] == '+' || digits[0] == '-' {
		digits = digits[1:]
	}
	if len(digits) > 1 && digits[0] == '0' && digits[1] != '.' {
		return false
	}
	return true
}

func decodeExplicit(pair []any) (Cell, error) {
	if len(pair) != 2 {
		return Cell{}, fmt.Errorf("easy-excel: array cell values are not supported (explicit cells must be [marker, value] pairs)")
	}
	marker, ok := pair[0].(string)
	if !ok {
		return Cell{}, fmt.Errorf("easy-excel: invalid explicit cell marker of type %T", pair[0])
	}
	v := pair[1]
	switch marker {
	case MarkString:
		return Cell{Kind: Str, Str: toString(v)}, nil
	case MarkFormula:
		s := toString(v)
		if len(s) > 0 && s[0] == '=' {
			s = s[1:]
		}
		return Cell{Kind: Formula, Str: s}, nil
	case MarkBool:
		switch b := v.(type) {
		case bool:
			return Cell{Kind: Boolean, Bool: b}, nil
		case int64:
			return Cell{Kind: Boolean, Bool: b != 0}, nil
		case float64:
			return Cell{Kind: Boolean, Bool: b != 0}, nil
		default:
			return Cell{Kind: Boolean, Bool: toString(v) != "" && toString(v) != "0"}, nil
		}
	case MarkNumeric:
		switch n := v.(type) {
		case int64:
			return Cell{Kind: Num, Num: float64(n)}, nil
		case int:
			return Cell{Kind: Num, Num: float64(n)}, nil
		case float64:
			return Cell{Kind: Num, Num: n}, nil
		case string:
			f, err := strconv.ParseFloat(n, 64)
			if err != nil {
				return Cell{}, fmt.Errorf("easy-excel: %q is not numeric", n)
			}
			return Cell{Kind: Num, Num: f}, nil
		default:
			return Cell{}, fmt.Errorf("easy-excel: cannot bind %T as numeric", v)
		}
	default:
		return Cell{}, fmt.Errorf("easy-excel: unknown explicit cell marker %q", marker)
	}
}

func toString(v any) string {
	switch t := v.(type) {
	case string:
		return t
	case nil:
		return ""
	case bool:
		if t {
			return "1"
		}
		return "0"
	case int64:
		return strconv.FormatInt(t, 10)
	case float64:
		return strconv.FormatFloat(t, 'G', -1, 64)
	default:
		return fmt.Sprint(t)
	}
}
