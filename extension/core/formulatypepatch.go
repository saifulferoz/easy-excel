package core

import (
	"archive/zip"
	"bytes"
	"io"
	"regexp"
)

// Numeric formula results must not be typed "str" (review blocker 1).
//
// excelize's SetCellFormula ends with an unconditional `c.T, c.IS = "str", nil`
// and exposes no way to reset it: every public value setter calls
// removeFormula(), so writing the value afterwards deletes the formula. A
// cached numeric result therefore lands as
//
//	<c r="C1" s="1" t="str"><f>SUM(A1:B1)</f><v>2500</v></c>
//
// In OOXML `t="str"` means "formula returning a string"; the numeric type is
// `n`, which is the default and written by omitting the attribute. The
// consequence is not cosmetic: a reader that trusts the cached value skips
// number formatting for string cells, so a #,##0 total renders 2500 rather
// than 2,500 — and excelize's own reader takes the `case "str": return c.V`
// path that bypasses formattedValue entirely.
//
// Rather than reach into excelize's internals, the saved container is patched
// the same way streaming auto-filters already are (filterpatch.go): entries
// are raw-copied except the worksheet parts, where the attribute is dropped
// from formula cells whose cached value is numeric. Cells whose result is a
// genuine string keep t="str" — the regexp only matches a <v> body that parses
// as an OOXML number.

// A formula cell carrying t="str" whose <v> is numeric. Deliberately narrow:
// it must be a <c> element with both an <f> and a <v>, and the value must look
// like a number (optionally signed, decimal or scientific). Anything else —
// a real string result, an error, a cell without a cached value — is left
// alone.
var reNumericStrFormula = regexp.MustCompile(
	`(<c\b[^>]*?)\s+t="str"((?:[^>]*)><f\b[^>]*>[^<]*</f><v>-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?</v></c>)`)

// needsFormulaTypePatch reports whether the sheet XML carries any cell the
// patch would rewrite, so a workbook without cached formulas skips the pass.
func needsFormulaTypePatch(b []byte) bool {
	return reNumericStrFormula.Match(b)
}

// patchNumericFormulaTypes rewrites the saved container, dropping t="str" from
// formula cells whose cached result is numeric.
func patchNumericFormulaTypes(src io.ReaderAt, size int64, dst io.Writer) error {
	zr, err := zip.NewReader(src, size)
	if err != nil {
		return err
	}
	zw := zip.NewWriter(dst)
	for _, f := range zr.File {
		if !isWorksheetPart(f.Name) {
			raw, err := f.OpenRaw()
			if err != nil {
				return err
			}
			hdr := f.FileHeader
			w, err := zw.CreateRaw(&hdr)
			if err != nil {
				return err
			}
			if _, err := io.Copy(w, raw); err != nil {
				return err
			}
			continue
		}
		r, err := f.Open()
		if err != nil {
			return err
		}
		b, err := io.ReadAll(r)
		r.Close()
		if err != nil {
			return err
		}
		b = reNumericStrFormula.ReplaceAll(b, []byte("$1$2"))
		w, err := zw.Create(f.Name)
		if err != nil {
			return err
		}
		if _, err := io.Copy(w, bytes.NewReader(b)); err != nil {
			return err
		}
	}
	return zw.Close()
}

func isWorksheetPart(name string) bool {
	return len(name) > len("xl/worksheets/") &&
		name[:len("xl/worksheets/")] == "xl/worksheets/" &&
		bytes.HasSuffix([]byte(name), []byte(".xml"))
}
