package core

import (
	"bytes"
	"encoding/xml"
	"fmt"
	"strings"

	"github.com/xuri/excelize/v2"

	"github.com/xiidea/easy-excel/extension/compat"
)

// Sparklines (PhpSpreadsheet 5.10 Worksheet\Sparkline). excelize writes them
// into the worksheet's extLst, which the StreamWriter does not carry, so they
// queue and apply on flush like the rest of the non-streamable surface
// (COMPAT.md divergence 11).

// AddSparklineGroup queues one sparkline group (JSON spec, see
// compat.TranslateSparklineGroup). The spec is validated now so a bad group
// fails at the call site rather than at save.
func (w *Workbook) AddSparklineGroup(sheet, jsonSpec string) error {
	if _, err := compat.TranslateSparklineGroup(jsonSpec); err != nil {
		return err
	}
	return w.queueOp(sheet, pendingOp{kind: opSparkline, s1: jsonSpec})
}

// applyOpSparkline executes a queued sparkline group in random-access mode.
func (w *Workbook) applyOpSparkline(sheet string, op pendingOp) error {
	if op.kind != opSparkline {
		return fmt.Errorf("easy-excel: unhandled op kind %d", op.kind)
	}
	opts, err := compat.TranslateSparklineGroup(op.s1)
	if err != nil {
		return err
	}
	return w.f.AddSparkline(sheet, opts)
}

// extURISparklineGroups identifies the worksheet extLst entry that carries
// x14:sparklineGroups.
const extURISparklineGroups = "{05C60535-1F16-4fd2-B633-F4F36F0B64E0}"

// LoadedSparklineXML returns the raw sparkline <ext> element a loaded
// worksheet carried in its source file, or "" when it had none (and always
// for workbooks created in memory). The shim parses it with
// Reader\Xlsx\Sparklines so getSparklineGroupCollection() reports the
// file's groups.
func (w *Workbook) LoadedSparklineXML(sheet string) (string, error) {
	w.mu.Lock()
	defer w.mu.Unlock()
	if w.closed {
		return "", errClosed
	}
	if _, err := w.state(sheet); err != nil {
		return "", err
	}
	return w.loadedSparklines[sheet], nil
}

// scanLoadedSparklines collects each worksheet's sparkline <ext> element at
// open time. It is a byte search over the parts excelize already holds in
// memory, not an XML parse, so files without sparklines pay one scan per
// sheet. Parts above excelize's in-memory limit (UnzipXMLSizeLimit) live in
// temp files excelize does not expose; those sheets report no loaded
// sparklines, although the file's own sparklines are still preserved on
// save (COMPAT.md divergence 35).
func scanLoadedSparklines(f *excelize.File) map[string]string {
	out := map[string]string{}
	wbRaw, relsRaw := pkgPart(f, "xl/workbook.xml"), pkgPart(f, "xl/_rels/workbook.xml.rels")
	if wbRaw == nil || relsRaw == nil {
		return out
	}
	var wb wbXML
	var rels relsXML
	if xml.Unmarshal(wbRaw, &wb) != nil || xml.Unmarshal(relsRaw, &rels) != nil {
		return out
	}
	targets := map[string]string{}
	for _, r := range rels.Relationship {
		targets[r.ID] = r.Target
	}
	for _, s := range wb.Sheets.Sheet {
		target, ok := targets[s.RID]
		if !ok {
			continue
		}
		part := strings.TrimPrefix(target, "/")
		if !strings.HasPrefix(part, "xl/") {
			part = "xl/" + part
		}
		if ext := extElement(pkgPart(f, part), extURISparklineGroups); ext != "" {
			out[s.Name] = ext
		}
	}
	return out
}

func pkgPart(f *excelize.File, name string) []byte {
	v, ok := f.Pkg.Load(name)
	if !ok {
		return nil
	}
	b, _ := v.([]byte)
	return b
}

// extElement cuts the <ext uri="…"> element (any prefix) out of a worksheet
// part. ext elements do not nest, so the first matching close tag after the
// opening one ends it.
func extElement(data []byte, uri string) string {
	at := bytes.Index(data, []byte(uri))
	if at < 0 {
		return ""
	}
	start := bytes.LastIndexByte(data[:at], '<')
	if start < 0 {
		return ""
	}
	nameEnd := start + 1
	for nameEnd < at && data[nameEnd] != ' ' && data[nameEnd] != '\t' && data[nameEnd] != '\n' && data[nameEnd] != '\r' {
		nameEnd++
	}
	qname := string(data[start+1 : nameEnd])
	if qname != "ext" && !strings.HasSuffix(qname, ":ext") {
		return ""
	}
	closeTag := []byte("</" + qname + ">")
	end := bytes.Index(data[at:], closeTag)
	if end < 0 {
		return ""
	}
	return string(data[start : at+end+len(closeTag)])
}
