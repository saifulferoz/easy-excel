// Package exio enforces the file-path policy (PLAN.md §8): load/save are
// restricted to an allowlist of base directories, paths are cleaned before
// checking, and URL schemes are rejected (no SSRF surface in v1).
// The php:// streaming idiom is handled in the PHP shim via a temp file, so
// only real filesystem paths reach this layer.
package exio

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

// ErrDenied is returned when a path falls outside the configured policy.
var ErrDenied = errors.New("easy-excel: path denied by policy")

// Policy validates filesystem paths against allowed base directories.
// An empty AllowedPaths list permits any local path (open_basedir is still
// enforced by PHP itself before paths reach the extension).
type Policy struct {
	allowed []string // absolute, cleaned base directories
}

// NewPolicy builds a policy from a list of base directories.
func NewPolicy(bases []string) (*Policy, error) {
	p := &Policy{}
	for _, b := range bases {
		b = strings.TrimSpace(b)
		if b == "" {
			continue
		}
		abs, err := filepath.Abs(filepath.Clean(b))
		if err != nil {
			return nil, fmt.Errorf("easy-excel: invalid allowed path %q: %w", b, err)
		}
		p.allowed = append(p.allowed, abs)
	}
	return p, nil
}

// FromEnv builds the policy from EASY_EXCEL_ALLOWED_PATHS
// (colon-separated, like PATH). Unset means "no extension-level restriction".
func FromEnv() (*Policy, error) {
	v := os.Getenv("EASY_EXCEL_ALLOWED_PATHS")
	if v == "" {
		return &Policy{}, nil
	}
	return NewPolicy(strings.Split(v, ":"))
}

// Resolve validates p and returns its cleaned absolute form.
func (pl *Policy) Resolve(p string) (string, error) {
	if p == "" {
		return "", fmt.Errorf("%w: empty path", ErrDenied)
	}
	if i := strings.Index(p, "://"); i >= 0 {
		return "", fmt.Errorf("%w: URL schemes are not supported (%q)", ErrDenied, p[:i+3])
	}
	abs, err := filepath.Abs(filepath.Clean(p))
	if err != nil {
		return "", fmt.Errorf("%w: %v", ErrDenied, err)
	}
	if len(pl.allowed) == 0 {
		return abs, nil
	}
	for _, base := range pl.allowed {
		if abs == base || strings.HasPrefix(abs, base+string(filepath.Separator)) {
			return abs, nil
		}
	}
	return "", fmt.Errorf("%w: %q is outside EASY_EXCEL_ALLOWED_PATHS", ErrDenied, p)
}
