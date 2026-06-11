package core

import (
	"bufio"
	"encoding/csv"
	"io"

	"github.com/xuri/excelize/v2"
)

var utf8BOM = []byte{0xEF, 0xBB, 0xBF}

// writeCsvRows streams formatted sheet rows to out via the forward iterator,
// so CSV export is constant-memory regardless of sheet size.
func writeCsvRows(f *excelize.File, out io.Writer, sheet string, opts CsvOptions) error {
	bw := bufio.NewWriterSize(out, 1<<20)
	if opts.UseBOM {
		if _, err := bw.Write(utf8BOM); err != nil {
			return err
		}
	}
	cw := csv.NewWriter(bw)
	if opts.Delimiter != 0 {
		cw.Comma = opts.Delimiter
	}
	cw.UseCRLF = opts.UseCRLF

	rows, err := f.Rows(sheet)
	if err != nil {
		return err
	}
	defer rows.Close()
	for rows.Next() {
		cols, err := rows.Columns()
		if err != nil {
			return err
		}
		if opts.GuardFormula {
			for i, c := range cols {
				cols[i] = guardFormula(c)
			}
		}
		if err := cw.Write(cols); err != nil {
			return err
		}
	}
	cw.Flush()
	if err := cw.Error(); err != nil {
		return err
	}
	return bw.Flush()
}

// guardFormula applies the OWASP CSV-injection mitigation: cells starting
// with =, +, -, @, TAB or CR are prefixed with a single quote. Opt-in,
// matching PhpSpreadsheet's default of writing values verbatim.
func guardFormula(s string) string {
	if s == "" {
		return s
	}
	switch s[0] {
	case '=', '+', '-', '@', '\t', '\r':
		return "'" + s
	}
	return s
}
