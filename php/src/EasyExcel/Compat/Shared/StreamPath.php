<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Shared;

/**
 * easy-excel internal (not a PhpSpreadsheet class — do not confuse with
 * Shared\File). The native extension does raw OS file I/O and rejects any
 * scheme-qualified path, so URLs handled by PHP's stream layer (php://,
 * file://, or userland wrappers like gaufrette://) must be staged through
 * a local temp file by the shim before they reach the extension.
 */
final class StreamPath
{
    /** True when $path must go through PHP's stream layer instead of the extension. */
    public static function isWrapped(string $path): bool
    {
        return \preg_match('#^[a-zA-Z][a-zA-Z0-9.+-]*://#', $path) === 1;
    }
}
