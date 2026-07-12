package core

import "testing"

func TestEnvMiB(t *testing.T) {
	const key = "EASY_EXCEL_TEST_UNZIP_LIMIT"

	t.Run("unset falls back to default", func(t *testing.T) {
		if got := envMiB(key, defaultUnzipSizeLimit); got != defaultUnzipSizeLimit {
			t.Fatalf("got %d, want %d", got, defaultUnzipSizeLimit)
		}
	})

	t.Run("valid MiB value is converted to bytes", func(t *testing.T) {
		t.Setenv(key, "4096")
		if got := envMiB(key, defaultUnzipSizeLimit); got != 4096<<20 {
			t.Fatalf("got %d, want %d", got, int64(4096)<<20)
		}
	})

	t.Run("invalid values fall back to default", func(t *testing.T) {
		for _, v := range []string{"0", "-5", "abc", ""} {
			t.Setenv(key, v)
			if got := envMiB(key, defaultUnzipXMLSizeLimit); got != defaultUnzipXMLSizeLimit {
				t.Fatalf("value %q: got %d, want default %d", v, got, defaultUnzipXMLSizeLimit)
			}
		}
	})
}
