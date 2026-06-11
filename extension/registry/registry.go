// Package registry maps opaque int64 handles to live workbook objects.
//
// Handles are random (capability-style, PLAN.md §8): a stale or forged handle
// from another request errors instead of aliasing someone else's workbook.
// The table is sharded to avoid global lock contention in FrankenPHP worker
// mode, and a janitor closes entries idle longer than the TTL as a backstop
// for PHP code that never calls close (PLAN.md §7.6).
package registry

import (
	"crypto/rand"
	"encoding/binary"
	"errors"
	"sync"
	"time"
)

const shardCount = 16

// ErrNotFound is returned for unknown, stale, or foreign handles.
var ErrNotFound = errors.New("easy-excel: unknown or expired workbook handle")

// Closer is implemented by registered values needing cleanup on eviction.
type Closer interface {
	Close() error
}

type entry struct {
	value    any
	lastUsed time.Time
}

type shard struct {
	mu      sync.RWMutex
	entries map[int64]*entry
}

// Registry is a sharded handle table with idle-TTL eviction.
type Registry struct {
	shards [shardCount]shard
	ttl    time.Duration
	stop   chan struct{}
	once   sync.Once
}

// New creates a registry. idleTTL <= 0 disables the janitor.
func New(idleTTL time.Duration) *Registry {
	r := &Registry{ttl: idleTTL, stop: make(chan struct{})}
	for i := range r.shards {
		r.shards[i].entries = make(map[int64]*entry)
	}
	if idleTTL > 0 {
		go r.janitor()
	}
	return r
}

func (r *Registry) shardFor(h int64) *shard {
	return &r.shards[uint64(h)%shardCount]
}

// Put registers v and returns its new random handle.
func (r *Registry) Put(v any) int64 {
	for {
		h := randomHandle()
		s := r.shardFor(h)
		s.mu.Lock()
		if _, exists := s.entries[h]; !exists {
			s.entries[h] = &entry{value: v, lastUsed: time.Now()}
			s.mu.Unlock()
			return h
		}
		s.mu.Unlock()
	}
}

// Get returns the value for h and refreshes its idle timer.
func (r *Registry) Get(h int64) (any, error) {
	s := r.shardFor(h)
	s.mu.RLock()
	e, ok := s.entries[h]
	if ok {
		e.lastUsed = time.Now()
	}
	s.mu.RUnlock()
	if !ok {
		return nil, ErrNotFound
	}
	return e.value, nil
}

// Remove unregisters h and returns its value, closing is the caller's job.
func (r *Registry) Remove(h int64) (any, error) {
	s := r.shardFor(h)
	s.mu.Lock()
	e, ok := s.entries[h]
	if ok {
		delete(s.entries, h)
	}
	s.mu.Unlock()
	if !ok {
		return nil, ErrNotFound
	}
	return e.value, nil
}

// Len reports the number of live handles.
func (r *Registry) Len() int {
	n := 0
	for i := range r.shards {
		s := &r.shards[i]
		s.mu.RLock()
		n += len(s.entries)
		s.mu.RUnlock()
	}
	return n
}

// Shutdown stops the janitor and closes every remaining entry.
func (r *Registry) Shutdown() {
	r.once.Do(func() { close(r.stop) })
	for i := range r.shards {
		s := &r.shards[i]
		s.mu.Lock()
		for h, e := range s.entries {
			if c, ok := e.value.(Closer); ok {
				_ = c.Close()
			}
			delete(s.entries, h)
		}
		s.mu.Unlock()
	}
}

func (r *Registry) janitor() {
	tick := time.NewTicker(r.ttl / 4)
	defer tick.Stop()
	for {
		select {
		case <-r.stop:
			return
		case <-tick.C:
			r.evictIdle(time.Now().Add(-r.ttl))
		}
	}
}

func (r *Registry) evictIdle(deadline time.Time) {
	for i := range r.shards {
		s := &r.shards[i]
		var victims []Closer
		s.mu.Lock()
		for h, e := range s.entries {
			if e.lastUsed.Before(deadline) {
				if c, ok := e.value.(Closer); ok {
					victims = append(victims, c)
				}
				delete(s.entries, h)
			}
		}
		s.mu.Unlock()
		for _, c := range victims {
			_ = c.Close()
		}
	}
}

func randomHandle() int64 {
	var b [8]byte
	if _, err := rand.Read(b[:]); err != nil {
		panic(err) // crypto/rand failure is unrecoverable
	}
	h := int64(binary.LittleEndian.Uint64(b[:]) &^ (1 << 63))
	if h == 0 {
		return 1
	}
	return h
}
