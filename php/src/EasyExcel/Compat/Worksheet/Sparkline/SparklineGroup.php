<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet\Sparkline;

use EasyExcel\Compat\Cell\CellAddress;
use EasyExcel\Compat\Exception;

/**
 * A group of sparklines sharing one format, PhpSpreadsheet 5.10 style.
 *
 * Groups are sent to the extension when Writer\Xlsx saves, not when they are
 * added, because upstream hands the group back from Worksheet::addSparkline()
 * for further formatting. Once written a group (and its sparklines) is
 * frozen: excelize has no way to amend or remove a written group, so a later
 * change throws rather than silently missing the next save.
 *
 * excelize renders the type, the display toggles (markers, high, low, first,
 * last, negative, x-axis), right-to-left and the series colour. Line weight,
 * hidden-cell and empty-cell modes, axis scaling and the non-series colours
 * are stored and round-trip through the getters but use excelize's preset
 * values in the file; the presets equal upstream's defaults, so only
 * customised values diverge (COMPAT.md divergence 32).
 */
class SparklineGroup
{
    public const EMPTY_AS_GAP = 'gap';
    public const EMPTY_AS_ZERO = 'zero';
    public const EMPTY_AS_SPAN = 'span';

    public const AXIS_INDIVIDUAL = 'individual';
    public const AXIS_GROUP = 'group';
    public const AXIS_CUSTOM = 'custom';

    private SparklineType $type = SparklineType::Line;

    /** @var list<Sparkline> */
    private array $sparklines = [];

    private float $lineWeight = 0.75;

    private bool $displayMarkers = false;

    private bool $displayHigh = false;

    private bool $displayLow = false;

    private bool $displayFirst = false;

    private bool $displayLast = false;

    private bool $displayNegative = false;

    private bool $displayXAxis = false;

    private bool $displayHidden = false;

    private bool $rightToLeft = false;

    private string $displayEmptyCellsAs = self::EMPTY_AS_GAP;

    private string $minAxisType = self::AXIS_INDIVIDUAL;

    private string $maxAxisType = self::AXIS_INDIVIDUAL;

    private ?float $manualMin = null;

    private ?float $manualMax = null;

    private ?string $colorSeries = 'FF376092';

    private ?string $colorNegative = 'FFD00000';

    private ?string $colorAxis = 'FF000000';

    private ?string $colorMarkers = 'FFD00000';

    private ?string $colorFirst = 'FFD00000';

    private ?string $colorLast = 'FFD00000';

    private ?string $colorHigh = 'FFD00000';

    private ?string $colorLow = 'FFD00000';

    private bool $written = false;

    public function getType(): SparklineType
    {
        return $this->type;
    }

    public function setType(SparklineType $type): self
    {
        $this->assertMutable();
        $this->type = $type;

        return $this;
    }

    /** @return list<Sparkline> */
    public function getSparklines(): array
    {
        return $this->sparklines;
    }

    public function addSparkline(Sparkline $sparkline): self
    {
        $this->assertMutable();
        $this->sparklines[] = $sparkline;

        return $this;
    }

    public function createSparkline(CellAddress|string $location, string $dataRange): self
    {
        return $this->addSparkline(new Sparkline($location, $dataRange));
    }

    /** @param array<Sparkline> $sparklines */
    public function setSparklines(array $sparklines): self
    {
        $this->assertMutable();
        $this->sparklines = \array_values($sparklines);

        return $this;
    }

    public function getLineWeight(): float
    {
        return $this->lineWeight;
    }

    public function setLineWeight(float $lineWeight): self
    {
        $this->assertMutable();
        $this->lineWeight = $lineWeight;

        return $this;
    }

    public function getDisplayMarkers(): bool
    {
        return $this->displayMarkers;
    }

    public function setDisplayMarkers(bool $displayMarkers): self
    {
        $this->assertMutable();
        $this->displayMarkers = $displayMarkers;

        return $this;
    }

    public function getDisplayHigh(): bool
    {
        return $this->displayHigh;
    }

    public function setDisplayHigh(bool $displayHigh): self
    {
        $this->assertMutable();
        $this->displayHigh = $displayHigh;

        return $this;
    }

    public function getDisplayLow(): bool
    {
        return $this->displayLow;
    }

    public function setDisplayLow(bool $displayLow): self
    {
        $this->assertMutable();
        $this->displayLow = $displayLow;

        return $this;
    }

    public function getDisplayFirst(): bool
    {
        return $this->displayFirst;
    }

    public function setDisplayFirst(bool $displayFirst): self
    {
        $this->assertMutable();
        $this->displayFirst = $displayFirst;

        return $this;
    }

    public function getDisplayLast(): bool
    {
        return $this->displayLast;
    }

    public function setDisplayLast(bool $displayLast): self
    {
        $this->assertMutable();
        $this->displayLast = $displayLast;

        return $this;
    }

    public function getDisplayNegative(): bool
    {
        return $this->displayNegative;
    }

    public function setDisplayNegative(bool $displayNegative): self
    {
        $this->assertMutable();
        $this->displayNegative = $displayNegative;

        return $this;
    }

    public function getDisplayXAxis(): bool
    {
        return $this->displayXAxis;
    }

    public function setDisplayXAxis(bool $displayXAxis): self
    {
        $this->assertMutable();
        $this->displayXAxis = $displayXAxis;

        return $this;
    }

    public function getDisplayHidden(): bool
    {
        return $this->displayHidden;
    }

    public function setDisplayHidden(bool $displayHidden): self
    {
        $this->assertMutable();
        $this->displayHidden = $displayHidden;

        return $this;
    }

    public function getRightToLeft(): bool
    {
        return $this->rightToLeft;
    }

    public function setRightToLeft(bool $rightToLeft): self
    {
        $this->assertMutable();
        $this->rightToLeft = $rightToLeft;

        return $this;
    }

    public function getDisplayEmptyCellsAs(): string
    {
        return $this->displayEmptyCellsAs;
    }

    public function setDisplayEmptyCellsAs(string $displayEmptyCellsAs): self
    {
        $this->assertMutable();
        $this->displayEmptyCellsAs = $displayEmptyCellsAs;

        return $this;
    }

    public function getMinAxisType(): string
    {
        return $this->minAxisType;
    }

    public function setMinAxisType(string $minAxisType): self
    {
        $this->assertMutable();
        $this->minAxisType = $minAxisType;

        return $this;
    }

    public function getMaxAxisType(): string
    {
        return $this->maxAxisType;
    }

    public function setMaxAxisType(string $maxAxisType): self
    {
        $this->assertMutable();
        $this->maxAxisType = $maxAxisType;

        return $this;
    }

    public function getManualMin(): ?float
    {
        return $this->manualMin;
    }

    public function setManualMin(?float $manualMin): self
    {
        $this->assertMutable();
        $this->manualMin = $manualMin;

        return $this;
    }

    public function getManualMax(): ?float
    {
        return $this->manualMax;
    }

    public function setManualMax(?float $manualMax): self
    {
        $this->assertMutable();
        $this->manualMax = $manualMax;

        return $this;
    }

    public function getColorSeries(): ?string
    {
        return $this->colorSeries;
    }

    public function setColorSeries(?string $colorSeries): self
    {
        $this->assertMutable();
        $this->colorSeries = $colorSeries;

        return $this;
    }

    public function getColorNegative(): ?string
    {
        return $this->colorNegative;
    }

    public function setColorNegative(?string $colorNegative): self
    {
        $this->assertMutable();
        $this->colorNegative = $colorNegative;

        return $this;
    }

    public function getColorAxis(): ?string
    {
        return $this->colorAxis;
    }

    public function setColorAxis(?string $colorAxis): self
    {
        $this->assertMutable();
        $this->colorAxis = $colorAxis;

        return $this;
    }

    public function getColorMarkers(): ?string
    {
        return $this->colorMarkers;
    }

    public function setColorMarkers(?string $colorMarkers): self
    {
        $this->assertMutable();
        $this->colorMarkers = $colorMarkers;

        return $this;
    }

    public function getColorFirst(): ?string
    {
        return $this->colorFirst;
    }

    public function setColorFirst(?string $colorFirst): self
    {
        $this->assertMutable();
        $this->colorFirst = $colorFirst;

        return $this;
    }

    public function getColorLast(): ?string
    {
        return $this->colorLast;
    }

    public function setColorLast(?string $colorLast): self
    {
        $this->assertMutable();
        $this->colorLast = $colorLast;

        return $this;
    }

    public function getColorHigh(): ?string
    {
        return $this->colorHigh;
    }

    public function setColorHigh(?string $colorHigh): self
    {
        $this->assertMutable();
        $this->colorHigh = $colorHigh;

        return $this;
    }

    public function getColorLow(): ?string
    {
        return $this->colorLow;
    }

    public function setColorLow(?string $colorLow): self
    {
        $this->assertMutable();
        $this->colorLow = $colorLow;

        return $this;
    }

    /** @internal true once Writer\Xlsx has sent this group to the extension */
    public function isWritten(): bool
    {
        return $this->written;
    }

    /**
     * @internal freeze the group (and its sparklines) after it was written
     */
    public function markWritten(): void
    {
        $this->written = true;
        foreach ($this->sparklines as $sparkline) {
            $sparkline->markWritten();
        }
    }

    /**
     * @internal the extension's sparkline spec (extension/compat/sparkline.go)
     *
     * @return array<string, mixed>
     */
    public function toSpec(): array
    {
        return [
            'type' => $this->type->value,
            'sparklines' => \array_map(
                static fn (Sparkline $s): array => ['location' => $s->getLocation(), 'dataRange' => $s->getDataRange()],
                $this->sparklines,
            ),
            'markers' => $this->displayMarkers,
            'high' => $this->displayHigh,
            'low' => $this->displayLow,
            'first' => $this->displayFirst,
            'last' => $this->displayLast,
            'negative' => $this->displayNegative,
            'xAxis' => $this->displayXAxis,
            'rightToLeft' => $this->rightToLeft,
            'colorSeries' => $this->colorSeries ?? '',
        ];
    }

    private function assertMutable(): void
    {
        if ($this->written) {
            throw new Exception('Sparkline group was already written; excelize cannot amend a written group');
        }
    }
}
