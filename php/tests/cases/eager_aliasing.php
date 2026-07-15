<?php

declare(strict_types=1);

use function EasyExcel\compatSurfaceClasses;
use function EasyExcel\eagerAliasCompat;

/*
 * Eager surface binding. Lazy aliasing alone is not enough: PHP never
 * autoloads for parameter/instanceof checks, so a Compat object hitting a
 * consumer signature type-hinted with the PhpOffice name fatals unless the
 * alias is already registered. The test bootstrap runs eagerAliasCompat()
 * (default-on), so every implemented name must already be bound here —
 * asserted with autoload disabled to prove no lazy path is involved.
 */

return [
    'eager: implemented surface is bound at bootstrap without autoload' => function (): void {
        foreach ([
            'PhpOffice\\PhpSpreadsheet\\Spreadsheet',
            'PhpOffice\\PhpSpreadsheet\\Worksheet\\Worksheet',
            'PhpOffice\\PhpSpreadsheet\\Cell\\Coordinate',
            'PhpOffice\\PhpSpreadsheet\\Style\\Conditional',
        ] as $class) {
            T::ok(\class_exists($class, false), "$class not bound eagerly");
        }
        T::ok(
            \interface_exists('PhpOffice\\PhpSpreadsheet\\Cell\\IValueBinder', false),
            'interface not bound eagerly'
        );
    },

    'eager: every enumerated name resolves to its Compat implementation' => function (): void {
        $classes = compatSurfaceClasses();
        T::ok(count($classes) >= 50, 'suspiciously small Compat surface: ' . count($classes));
        foreach ($classes as $class) {
            T::ok(
                \class_exists($class, false) || \interface_exists($class, false),
                "$class enumerated but not bound"
            );
            $bound = (new ReflectionClass($class))->getName();
            T::ok(
                \str_starts_with($bound, 'EasyExcel\\Compat\\'),
                "$class bound to $bound, not a Compat class"
            );
        }
    },

    'eager: compat objects satisfy PhpOffice type declarations' => function (): void {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $takesWorksheet =
            static fn (PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): bool => true;

        // Fatals with a TypeError under lazy-only aliasing when nothing has
        // referenced the Worksheet class name before this call.
        T::ok($takesWorksheet($spreadsheet->getActiveSheet()), 'typed param rejected compat object');
        T::ok(
            $spreadsheet->getActiveSheet() instanceof PhpOffice\PhpSpreadsheet\Worksheet\Worksheet,
            'instanceof rejected compat object'
        );
    },

    'eager: idempotent and a no-op for off mode' => function (): void {
        T::same(0, eagerAliasCompat('strict'), 'second run must find everything bound');
        T::same(0, eagerAliasCompat('off'), 'off mode must not alias');
    },
];
