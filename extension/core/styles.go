package core

import (
	"sync"

	"github.com/xuri/excelize/v2"
)

// styleInterner memoizes excelize style IDs per number-format code
// (PLAN.md §6): repeated applyFromArray-style calls hit a map instead of
// growing the stylesheet. Reset on degrade because style IDs belong to the
// underlying excelize.File instance.
type styleInterner struct {
	mu       sync.Mutex
	byNumFmt map[string]int
}

func (si *styleInterner) numFmtID(f *excelize.File, code string) (int, error) {
	si.mu.Lock()
	defer si.mu.Unlock()
	if id, ok := si.byNumFmt[code]; ok {
		return id, nil
	}
	id, err := f.NewStyle(&excelize.Style{CustomNumFmt: &code})
	if err != nil {
		return 0, err
	}
	if si.byNumFmt == nil {
		si.byNumFmt = make(map[string]int)
	}
	si.byNumFmt[code] = id
	return id, nil
}

func (si *styleInterner) reset() {
	si.mu.Lock()
	si.byNumFmt = nil
	si.mu.Unlock()
}
