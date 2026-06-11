// Package limits implements load control for FrankenPHP worker mode
// (PLAN.md §7): a weighted admission semaphore around heavy operations and a
// process-wide memory budget acting as a circuit breaker. Overload is
// surfaced to PHP as a typed error so applications can shed load (HTTP 429)
// or dispatch to their existing queues instead of OOM-killing the worker.
package limits

import (
	"context"
	"errors"
	"fmt"
	"os"
	"runtime"
	"strconv"
	"sync/atomic"
	"time"

	"golang.org/x/sync/semaphore"
)

// ErrOverloaded maps to the EasyExcel\Exception\Overloaded PHP exception.
var ErrOverloaded = errors.New("easy-excel: overloaded, try again later")

// Gate combines the heavy-operation semaphore and the memory budget.
type Gate struct {
	sem            *semaphore.Weighted
	acquireTimeout time.Duration
	memBudget      int64
	memUsed        atomic.Int64
}

// Config controls a Gate. Zero values pick safe defaults.
type Config struct {
	MaxConcurrent  int64         // heavy ops in flight; default max(2, NumCPU)
	AcquireTimeout time.Duration // wait before ErrOverloaded; default 30s
	MemoryBudget   int64         // bytes of estimated live workbook data; default 512MiB
}

// FromEnv reads EASY_EXCEL_MAX_CONCURRENT, EASY_EXCEL_ACQUIRE_TIMEOUT_MS and
// EASY_EXCEL_MEMORY_BUDGET_MB, falling back to defaults for unset/invalid values.
func FromEnv() Config {
	cfg := Config{}
	if v, err := strconv.ParseInt(os.Getenv("EASY_EXCEL_MAX_CONCURRENT"), 10, 64); err == nil && v > 0 {
		cfg.MaxConcurrent = v
	}
	if v, err := strconv.ParseInt(os.Getenv("EASY_EXCEL_ACQUIRE_TIMEOUT_MS"), 10, 64); err == nil && v > 0 {
		cfg.AcquireTimeout = time.Duration(v) * time.Millisecond
	}
	if v, err := strconv.ParseInt(os.Getenv("EASY_EXCEL_MEMORY_BUDGET_MB"), 10, 64); err == nil && v > 0 {
		cfg.MemoryBudget = v << 20
	}
	return cfg
}

// NewGate builds a Gate from cfg, applying defaults.
func NewGate(cfg Config) *Gate {
	if cfg.MaxConcurrent <= 0 {
		cfg.MaxConcurrent = int64(max(2, runtime.NumCPU()))
	}
	if cfg.AcquireTimeout <= 0 {
		cfg.AcquireTimeout = 30 * time.Second
	}
	if cfg.MemoryBudget <= 0 {
		cfg.MemoryBudget = 512 << 20
	}
	return &Gate{
		sem:            semaphore.NewWeighted(cfg.MaxConcurrent),
		acquireTimeout: cfg.AcquireTimeout,
		memBudget:      cfg.MemoryBudget,
	}
}

// AcquireHeavy blocks until a heavy-operation slot is free or the timeout
// elapses. The returned release function must be called exactly once.
func (g *Gate) AcquireHeavy() (release func(), err error) {
	ctx, cancel := context.WithTimeout(context.Background(), g.acquireTimeout)
	defer cancel()
	if err := g.sem.Acquire(ctx, 1); err != nil {
		return nil, fmt.Errorf("%w (waited %s for a free slot)", ErrOverloaded, g.acquireTimeout)
	}
	return func() { g.sem.Release(1) }, nil
}

// ReserveMemory accounts estimated bytes against the budget, failing fast
// when the budget is exhausted. Negative deltas always succeed (release).
func (g *Gate) ReserveMemory(delta int64) error {
	used := g.memUsed.Add(delta)
	if delta > 0 && used > g.memBudget {
		g.memUsed.Add(-delta)
		return fmt.Errorf("%w (memory budget %dMiB exhausted)", ErrOverloaded, g.memBudget>>20)
	}
	return nil
}

// MemoryUsed reports current accounted bytes (observability hook).
func (g *Gate) MemoryUsed() int64 { return g.memUsed.Load() }
