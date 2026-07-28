<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet;

use EasyExcel\Compat\Exception;

/**
 * easy-excel-native sparkline group: tiny in-cell charts backed by a data range.
 *
 * PhpSpreadsheet has no sparkline object model (Excel sparklines live in the
 * x14 extension list, which PhpSpreadsheet does not write), so this is an
 * easy-excel extra rather than a Compat class — see COMPAT.md. Attach it with
 * {@see Worksheet::addSparkline()}. Backed by excelize's AddSparkline.
 *
 * NB: the bootstrap aliases this to `PhpOffice\PhpSpreadsheet\Worksheet\Sparkline`
 * like every Compat class, but upstream has no such class — reference it as
 * `EasyExcel\Compat\Worksheet\Sparkline` so your code stays honest about being
 * easy-excel-specific (the aliased name only resolves under this extension).
 *
 * A group maps one location cell to one data range; pairs are added in order.
 * Example:
 *
 *   $ws->addSparkline(
 *       (new Sparkline(Sparkline::TYPE_COLUMN))
 *           ->addPair('G2', 'A2:F2')
 *           ->addPair('G3', 'A3:F3')
 *           ->setHigh(true)->setLow(true)
 *   );
 */
final class Sparkline
{
    public const TYPE_LINE = 'line';
    public const TYPE_COLUMN = 'column';
    public const TYPE_WIN_LOSS = 'win_loss';

    /** @var list<string> */
    private array $locations = [];

    /** @var list<string> */
    private array $dataRanges = [];

    /** @var array<string, bool|int|float|string> */
    private array $options = [];

    public function __construct(private string $type = self::TYPE_LINE)
    {
        if (!\in_array($type, [self::TYPE_LINE, self::TYPE_COLUMN, self::TYPE_WIN_LOSS], true)) {
            throw new Exception("easy-excel: unsupported sparkline type '$type' (want line|column|win_loss)");
        }
    }

    /** Map one location cell (e.g. 'G2') to its data range (e.g. 'A2:F2'). */
    public function addPair(string $location, string $dataRange): self
    {
        $this->locations[] = $location;
        $this->dataRanges[] = $dataRange;

        return $this;
    }

    public function setStyle(int $style): self
    {
        $this->options['style'] = $style;

        return $this;
    }

    public function setWeight(float $weight): self
    {
        $this->options['weight'] = $weight;

        return $this;
    }

    public function setHigh(bool $on = true): self
    {
        return $this->flag('high', $on);
    }

    public function setLow(bool $on = true): self
    {
        return $this->flag('low', $on);
    }

    public function setFirst(bool $on = true): self
    {
        return $this->flag('first', $on);
    }

    public function setLast(bool $on = true): self
    {
        return $this->flag('last', $on);
    }

    public function setNegative(bool $on = true): self
    {
        return $this->flag('negative', $on);
    }

    public function setMarkers(bool $on = true): self
    {
        return $this->flag('markers', $on);
    }

    public function setAxis(bool $on = true): self
    {
        return $this->flag('axis', $on);
    }

    public function setReverse(bool $on = true): self
    {
        return $this->flag('reverse', $on);
    }

    public function setSeriesColor(string $rgb): self
    {
        return $this->color('seriesColor', $rgb);
    }

    public function setNegativeColor(string $rgb): self
    {
        return $this->color('negativeColor', $rgb);
    }

    public function setMarkersColor(string $rgb): self
    {
        return $this->color('markersColor', $rgb);
    }

    public function setFirstColor(string $rgb): self
    {
        return $this->color('firstColor', $rgb);
    }

    public function setLastColor(string $rgb): self
    {
        return $this->color('lastColor', $rgb);
    }

    public function setHighColor(string $rgb): self
    {
        return $this->color('highColor', $rgb);
    }

    public function setLowColor(string $rgb): self
    {
        return $this->color('lowColor', $rgb);
    }

    /**
     * @internal builds the native sparkline spec for Native::addSparkline
     *
     * @return array<string, mixed>
     */
    public function buildSpec(): array
    {
        if ($this->locations === []) {
            throw new Exception('easy-excel: sparkline needs at least one location/dataRange pair (call addPair)');
        }

        return [
            'type' => $this->type,
            'location' => $this->locations,
            'dataRange' => $this->dataRanges,
        ] + $this->options;
    }

    private function flag(string $key, bool $on): self
    {
        $this->options[$key] = $on;

        return $this;
    }

    /** excelize wants a 6-hex-digit RGB (no leading #); normalise ARGB too. */
    private function color(string $key, string $rgb): self
    {
        $rgb = \ltrim($rgb, '#');
        if (\strlen($rgb) === 8) {
            $rgb = \substr($rgb, 2); // strip an ARGB alpha byte
        }
        if (!\preg_match('/^[0-9A-Fa-f]{6}$/', $rgb)) {
            throw new Exception("easy-excel: sparkline color must be RGB hex, got '$rgb'");
        }
        $this->options[$key] = \strtoupper($rgb);

        return $this;
    }
}
