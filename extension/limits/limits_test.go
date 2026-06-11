package limits

import (
	"errors"
	"testing"
	"time"
)

func TestAcquireHeavyBlocksAndTimesOut(t *testing.T) {
	g := NewGate(Config{MaxConcurrent: 1, AcquireTimeout: 50 * time.Millisecond})
	rel, err := g.AcquireHeavy()
	if err != nil {
		t.Fatal(err)
	}
	if _, err := g.AcquireHeavy(); !errors.Is(err, ErrOverloaded) {
		t.Fatalf("second acquire should overload, got %v", err)
	}
	rel()
	rel2, err := g.AcquireHeavy()
	if err != nil {
		t.Fatalf("after release acquire should succeed: %v", err)
	}
	rel2()
}

func TestMemoryBudget(t *testing.T) {
	g := NewGate(Config{MemoryBudget: 100})
	if err := g.ReserveMemory(80); err != nil {
		t.Fatal(err)
	}
	if err := g.ReserveMemory(30); !errors.Is(err, ErrOverloaded) {
		t.Fatalf("over-budget reserve must fail, got %v", err)
	}
	if got := g.MemoryUsed(); got != 80 {
		t.Fatalf("failed reserve must roll back, used=%d", got)
	}
	if err := g.ReserveMemory(-80); err != nil {
		t.Fatal(err)
	}
	if got := g.MemoryUsed(); got != 0 {
		t.Fatalf("release accounting wrong, used=%d", got)
	}
}

func TestDefaults(t *testing.T) {
	g := NewGate(Config{})
	rel, err := g.AcquireHeavy()
	if err != nil {
		t.Fatal(err)
	}
	rel()
	if err := g.ReserveMemory(1 << 20); err != nil {
		t.Fatal(err)
	}
}
