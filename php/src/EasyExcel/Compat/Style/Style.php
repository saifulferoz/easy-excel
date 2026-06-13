<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Style;

use EasyExcel\Compat\Worksheet\Worksheet;
use EasyExcel\Native;

/**
 * Style facade for one range (Phase 2). Component setters send partial style
 * specs straight to the extension; the Go side keeps a per-sheet style log
 * and merges overlapping partial styles in application order, so chained
 * setters and repeated getStyle() calls layer exactly like PhpSpreadsheet's
 * supervisor model. Styles queued before their rows are written are inlined
 * into the stream — no flush here, and no degrade for the usual
 * "style header → bulk write" report.
 */
class Style
{
    private ?Font $font = null;
    private ?Fill $fill = null;
    private ?Borders $borders = null;
    private ?Alignment $alignment = null;
    private ?Protection $protection = null;

    /** @var array<string, mixed> collected spec when detached (conditional styles) */
    private array $collected = [];

    /** non-null when this Style targets the workbook default (getDefaultStyle) */
    private ?\EasyExcel\Compat\Spreadsheet $defaultTarget = null;

    /** @var array<string, mixed>|null lazily fetched effective spec (read-back) */
    private ?array $nativeSpec = null;

    public function __construct(private ?Worksheet $worksheet, private string $range)
    {
    }

    /**
     * A detached Style collects its spec locally instead of styling cells;
     * used by Conditional::getStyle().
     */
    public static function detached(): self
    {
        return new self(null, '');
    }

    /** @internal the workbook-default style behind Spreadsheet::getDefaultStyle() */
    public static function defaultFor(\EasyExcel\Compat\Spreadsheet $spreadsheet): self
    {
        $style = new self(null, '');
        $style->defaultTarget = $spreadsheet;

        return $style;
    }

    /** @internal @return array<string, mixed> */
    public function getCollectedSpec(): array
    {
        return $this->collected;
    }

    /**
     * @internal one component of the effective spec: local writes win, then
     * the native stylesheet (read-back of loaded files), then defaults
     *
     * @return array<string, mixed>
     */
    public function nativeComponent(string $component): array
    {
        if ($this->worksheet === null) {
            return \is_array($this->collected[$component] ?? null) ? $this->collected[$component] : [];
        }
        if ($this->nativeSpec === null) {
            $topLeft = \explode(':', $this->range)[0];
            $this->nativeSpec = Native::getStyle(
                $this->worksheet->getParent()->getHandle(),
                $this->worksheet->getTitle(),
                $topLeft,
            );
        }
        $section = $this->nativeSpec[$component] ?? [];

        return \is_array($section) ? $section : [];
    }

    /**
     * @internal full effective spec of this style (duplicateStyle source)
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        if ($this->worksheet === null) {
            return $this->collected;
        }
        $topLeft = \explode(':', $this->range)[0];

        return Native::getStyle(
            $this->worksheet->getParent()->getHandle(),
            $this->worksheet->getTitle(),
            $topLeft,
        );
    }

    public function getFont(): Font
    {
        return $this->font ??= new Font($this);
    }

    public function getFill(): Fill
    {
        return $this->fill ??= new Fill($this);
    }

    public function getBorders(): Borders
    {
        return $this->borders ??= new Borders($this);
    }

    public function getAlignment(): Alignment
    {
        return $this->alignment ??= new Alignment($this);
    }

    public function getProtection(): Protection
    {
        return $this->protection ??= new Protection($this);
    }

    public function getNumberFormat(): NumberFormat
    {
        if ($this->worksheet === null) {
            return new NumberFormat(null, '', $this);
        }

        return new NumberFormat($this->worksheet, $this->range);
    }

    /**
     * Replaces the conditional-formatting rules for this style's range.
     *
     * @param list<Conditional> $conditionalStyles
     */
    public function setConditionalStyles(array $conditionalStyles): static
    {
        if ($this->worksheet === null) {
            throw new \EasyExcel\Compat\Exception('easy-excel: conditional styles need a worksheet range');
        }
        Native::setConditional(
            $this->worksheet->getParent()->getHandle(),
            $this->worksheet->getTitle(),
            $this->range,
            \array_map(static fn (Conditional $c): array => $c->toSpec(), $conditionalStyles),
        );
        $this->worksheet->rememberConditionalStyles($this->range, $conditionalStyles);

        return $this;
    }

    /**
     * Rules previously set on this exact range **in this session** (loaded
     * files are not introspected — COMPAT.md).
     *
     * @return list<Conditional>
     */
    public function getConditionalStyles(): array
    {
        if ($this->worksheet === null) {
            return [];
        }
        $session = $this->worksheet->recallConditionalStyles($this->range);
        if ($session !== []) {
            return $session;
        }
        // loaded files: hydrate from the native conditional formats
        $all = Native::getConditionals(
            $this->worksheet->getParent()->getHandle(),
            $this->worksheet->getTitle(),
        );

        return \array_map(Conditional::fromSpec(...), $all[$this->range] ?? []);
    }

    /** @param array<string, mixed> $styleArray PhpSpreadsheet's nested style array */
    public function applyFromArray(array $styleArray, bool $advancedBorders = true): static
    {
        $this->send($styleArray);

        return $this;
    }

    public function getQuotePrefix(): bool
    {
        return false;
    }

    /** @internal one partial spec component, e.g. ['font' => ['bold' => true]] */
    public function mergeComponent(string $component, array $values): void
    {
        $this->send([$component => $values]);
    }

    /** @param array<string, mixed> $spec */
    private function send(array $spec): void
    {
        if ($spec === []) {
            return;
        }
        $this->nativeSpec = null; // read-back cache is stale after any write
        if ($this->defaultTarget !== null) {
            $this->collected = self::deepMerge($this->collected, $spec);
            Native::setDefaultStyle($this->defaultTarget->getHandle(), $this->collected);

            return;
        }
        if ($this->worksheet === null) {
            $this->collected = self::deepMerge($this->collected, $spec);

            return;
        }
        Native::applyStyle(
            $this->worksheet->getParent()->getHandle(),
            $this->worksheet->getTitle(),
            $this->range,
            $spec,
        );
    }

    private static function deepMerge(array $base, array $patch): array
    {
        foreach ($patch as $k => $v) {
            if (\is_array($v) && isset($base[$k]) && \is_array($base[$k])) {
                $base[$k] = self::deepMerge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }
}
