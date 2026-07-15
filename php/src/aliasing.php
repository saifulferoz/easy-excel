<?php

declare(strict_types=1);

/*
 * Aliasing strategy for the PhpOffice\PhpSpreadsheet\* -> EasyExcel\Compat\*
 * bridge, factored into pure, unit-testable functions. bootstrap.php wires
 * these to the live environment; the test suite calls them directly.
 *
 * Modes (selected by aliasMode()):
 *
 *   off       Never alias. PhpOffice\PhpSpreadsheet\* resolves to the real
 *             package (or fatals if it isn't installed). Use this to run on
 *             stock PhpSpreadsheet, e.g. for A/B output comparison.
 *
 *   strict    All-or-nothing. Alias every Compat-implemented class; *throw*
 *             UnsupportedApiException on any PhpOffice\PhpSpreadsheet\* class
 *             the Compat layer lacks. A request is therefore served entirely
 *             by Compat or it fails — a handle-based workbook can never be
 *             mixed with a real object graph. This is the default when the
 *             native extension is loaded.
 *
 *   fallback  Hybrid escape hatch. Alias what Compat implements; defer every
 *             other PhpOffice\PhpSpreadsheet\* class to the real package
 *             (per class). Convenient for incremental adoption, but can mix
 *             object models within one request — opt in knowingly.
 */

namespace EasyExcel;

/**
 * Resolve the effective aliasing mode from environment + capability.
 *
 * @param string|false $env     Raw value of getenv('EASY_EXCEL_ALIAS')
 *                              (false when unset). Case-insensitive.
 * @param bool         $haveExt Whether the native easy_excel extension is loaded.
 *
 * @return 'off'|'strict'|'fallback'
 */
function aliasMode(string|false $env, bool $haveExt): string
{
    return match (\strtolower($env === false ? '' : $env)) {
        'off'      => 'off',
        'force'    => 'strict',            // force aliases even without the ext (tests)
        'strict'   => $haveExt ? 'strict' : 'off',
        'fallback' => $haveExt ? 'fallback' : 'off',
        default    => $haveExt ? 'strict' : 'off', // auto: prefer Compat when the ext is present
    };
}

/**
 * Map a class name to its Compat target.
 *
 * @return string|false|null
 *   string  FQN of the Compat class/interface to alias to
 *   false   a PhpOffice\PhpSpreadsheet\* class with no Compat implementation
 *   null    not a PhpOffice\PhpSpreadsheet\* class at all (ignore)
 */
function compatTarget(string $class): string|false|null
{
    if (!\str_starts_with($class, 'PhpOffice\\PhpSpreadsheet\\')) {
        return null;
    }
    $target = 'EasyExcel\\Compat\\' . \substr($class, 25);

    return (\class_exists($target) || \interface_exists($target)) ? $target : false;
}

/**
 * Decide what the autoloader should do for one class under a given mode.
 * Pure: no side effects, so every mode x class combination is unit-testable.
 *
 * @return array{0: 'ignore'|'alias'|'throw'|'defer', 1: ?string}
 *   ['ignore', null]      not a PhpOffice\PhpSpreadsheet\* class
 *   ['alias', <target>]   alias the class to the given Compat FQN
 *   ['throw', null]       strict mode, no Compat implementation -> fail loudly
 *   ['defer', null]       fallback mode, no Compat impl -> let the real package load it
 */
function aliasAction(string $mode, string $class): array
{
    $target = compatTarget($class);
    if ($target === null) {
        return ['ignore', null];
    }
    if ($target !== false) {
        return ['alias', $target];
    }

    return [$mode === 'strict' ? 'throw' : 'defer', null];
}

/**
 * Register the prepended autoloader that performs the aliasing for the given
 * mode. No-op for 'off'.
 */
function registerCompatAutoloader(string $mode): void
{
    if ($mode === 'off') {
        return;
    }

    \spl_autoload_register(static function (string $class) use ($mode): void {
        [$action, $target] = aliasAction($mode, $class);

        switch ($action) {
            case 'alias':
                \class_alias($target, $class);

                return;
            case 'throw':
                throw new UnsupportedApiException(\sprintf(
                    '%s is not implemented by the easy-excel Compat layer. Implement it, '
                    . 'or set EASY_EXCEL_ALIAS=off (or =fallback) to use real '
                    . 'phpoffice/phpspreadsheet for the whole request.',
                    $class,
                ));
            default: // 'ignore' | 'defer'
                return;
        }
    }, true, true);
}

/**
 * Enumerate every PhpOffice\PhpSpreadsheet\* name the Compat layer implements,
 * by scanning the Compat source tree. Cached per process; sorted so the
 * aliasing order is deterministic.
 *
 * (Not derived from .compat-surface.json — that file tracks the full upstream
 * API surface including classes Compat does not implement.)
 *
 * @return list<class-string>
 */
function compatSurfaceClasses(): array
{
    static $classes = null;
    if ($classes !== null) {
        return $classes;
    }

    $base = __DIR__ . '/EasyExcel/Compat';
    $classes = [];
    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $relative = \substr($file->getPathname(), \strlen($base) + 1, -4);
        $classes[] = 'PhpOffice\\PhpSpreadsheet\\' . \str_replace(\DIRECTORY_SEPARATOR, '\\', $relative);
    }
    \sort($classes);

    return $classes;
}

/**
 * Bind the whole Compat surface up front instead of waiting for autoload.
 *
 * Lazy aliasing has two holes the engine cannot paper over:
 *
 *  1. PHP never autoloads for parameter/return/instanceof checks, so a Compat
 *     object passed to consumer code type-hinted with the PhpOffice name
 *     (e.g. `function f(Worksheet $ws)`) throws a TypeError unless something
 *     else happened to reference that class first.
 *  2. composer's autoloader prepends itself, so a bootstrap loaded before
 *     vendor/autoload.php loses the race and the real package silently wins.
 *
 * Aliasing eagerly closes both: once a name is bound, neither the type check
 * nor composer ever consults an autoloader for it. Names that are already
 * defined (a real PhpSpreadsheet class loaded earlier) are left untouched.
 *
 * No-op for 'off'. Returns the number of aliases registered.
 */
function eagerAliasCompat(string $mode): int
{
    if ($mode === 'off') {
        return 0;
    }

    $aliased = 0;
    foreach (compatSurfaceClasses() as $class) {
        if (\class_exists($class, false) || \interface_exists($class, false)) {
            continue;
        }
        $target = compatTarget($class);
        if (\is_string($target)) {
            \class_alias($target, $class);
            ++$aliased;
        }
    }

    return $aliased;
}
