package registry

import (
	"sync"
	"sync/atomic"
	"testing"
	"time"
)

type closeSpy struct{ closed atomic.Bool }

func (c *closeSpy) Close() error { c.closed.Store(true); return nil }

func TestPutGetRemove(t *testing.T) {
	r := New(0)
	v := &closeSpy{}
	h := r.Put(v)
	if h == 0 {
		t.Fatal("handle must be non-zero")
	}
	got, err := r.Get(h)
	if err != nil || got != v {
		t.Fatalf("Get = %v, %v", got, err)
	}
	if _, err := r.Remove(h); err != nil {
		t.Fatalf("Remove: %v", err)
	}
	if _, err := r.Get(h); err != ErrNotFound {
		t.Fatalf("stale handle should be ErrNotFound, got %v", err)
	}
}

func TestForgedHandle(t *testing.T) {
	r := New(0)
	if _, err := r.Get(12345); err != ErrNotFound {
		t.Fatalf("forged handle should fail, got %v", err)
	}
}

func TestConcurrentAccess(t *testing.T) {
	r := New(0)
	var wg sync.WaitGroup
	for i := 0; i < 64; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for j := 0; j < 200; j++ {
				h := r.Put(&closeSpy{})
				if _, err := r.Get(h); err != nil {
					t.Error(err)
				}
				if _, err := r.Remove(h); err != nil {
					t.Error(err)
				}
			}
		}()
	}
	wg.Wait()
	if n := r.Len(); n != 0 {
		t.Fatalf("expected empty registry, %d left", n)
	}
}

func TestIdleEviction(t *testing.T) {
	r := New(0) // no janitor; drive eviction manually
	v := &closeSpy{}
	h := r.Put(v)
	r.evictIdle(time.Now().Add(time.Second)) // everything is "idle"
	if !v.closed.Load() {
		t.Fatal("evicted entry must be closed")
	}
	if _, err := r.Get(h); err != ErrNotFound {
		t.Fatal("evicted handle must be gone")
	}
}

func TestShutdownClosesAll(t *testing.T) {
	r := New(time.Hour)
	spies := make([]*closeSpy, 10)
	for i := range spies {
		spies[i] = &closeSpy{}
		r.Put(spies[i])
	}
	r.Shutdown()
	for i, s := range spies {
		if !s.closed.Load() {
			t.Fatalf("spy %d not closed", i)
		}
	}
	if r.Len() != 0 {
		t.Fatal("registry not empty after shutdown")
	}
}
