package exio

import (
	"errors"
	"path/filepath"
	"testing"
)

func TestUnrestrictedPolicy(t *testing.T) {
	p, _ := NewPolicy(nil)
	got, err := p.Resolve("/tmp/foo.xlsx")
	if err != nil || got != filepath.Clean("/tmp/foo.xlsx") {
		t.Fatalf("got %q, %v", got, err)
	}
}

func TestSchemeRejected(t *testing.T) {
	p, _ := NewPolicy(nil)
	for _, bad := range []string{"http://x/a.xlsx", "phar://evil", "s3://bucket/k"} {
		if _, err := p.Resolve(bad); !errors.Is(err, ErrDenied) {
			t.Fatalf("%q should be denied, got %v", bad, err)
		}
	}
}

func TestAllowlistAndTraversal(t *testing.T) {
	p, err := NewPolicy([]string{"/var/exports"})
	if err != nil {
		t.Fatal(err)
	}
	if _, err := p.Resolve("/var/exports/a/b.xlsx"); err != nil {
		t.Fatalf("inside allowlist should pass: %v", err)
	}
	for _, bad := range []string{
		"/var/exports/../secrets/x.xlsx", // traversal escapes base
		"/etc/passwd",
		"/var/exportsevil/x.xlsx", // prefix trick must not match
	} {
		if _, err := p.Resolve(bad); !errors.Is(err, ErrDenied) {
			t.Fatalf("%q should be denied, got %v", bad, err)
		}
	}
	// traversal that stays inside the base is fine after cleaning
	if _, err := p.Resolve("/var/exports/a/../b.xlsx"); err != nil {
		t.Fatalf("clean path inside base should pass: %v", err)
	}
}

func TestEmptyPath(t *testing.T) {
	p, _ := NewPolicy(nil)
	if _, err := p.Resolve(""); !errors.Is(err, ErrDenied) {
		t.Fatal("empty path must be denied")
	}
}
