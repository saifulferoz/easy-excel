<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet\PivotTable;

/**
 * How one source field is grouped in a pivot table, PhpSpreadsheet 5.10 style:
 * fixed-width numeric buckets, or calendar parts for dates.
 *
 * excelize's pivot tables have no field grouping, so a group is stored on the
 * cache definition and round-trips through the getters but is not written to
 * the file (COMPAT.md divergence 33).
 */
class PivotFieldGroup
{
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_DATE = 'date';

    // rangePr@groupBy values
    public const GROUP_BY_SECONDS = 'seconds';
    public const GROUP_BY_MINUTES = 'minutes';
    public const GROUP_BY_HOURS = 'hours';
    public const GROUP_BY_DAYS = 'days';
    public const GROUP_BY_MONTHS = 'months';
    public const GROUP_BY_QUARTERS = 'quarters';
    public const GROUP_BY_YEARS = 'years';

    private float $interval = 1.0;

    private ?float $startNum = null;

    private ?float $endNum = null;

    /** @var list<string> calendar units, coarse to fine */
    private array $groupBy = [];

    private ?string $startDate = null;

    private ?string $endDate = null;

    private function __construct(private readonly string $type)
    {
    }

    public static function numeric(float $interval, ?float $startNum = null, ?float $endNum = null): self
    {
        $group = new self(self::TYPE_NUMERIC);
        $group->interval = $interval;
        $group->startNum = $startNum;
        $group->endNum = $endNum;

        return $group;
    }

    /** @param string|array<string> $groupBy one or more GROUP_BY_* constants */
    public static function date(array|string $groupBy, ?string $startDate = null, ?string $endDate = null): self
    {
        $group = new self(self::TYPE_DATE);
        $group->groupBy = \is_array($groupBy) ? \array_values($groupBy) : [$groupBy];
        $group->startDate = $startDate;
        $group->endDate = $endDate;

        return $group;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNumeric(): bool
    {
        return $this->type === self::TYPE_NUMERIC;
    }

    public function isDate(): bool
    {
        return $this->type === self::TYPE_DATE;
    }

    public function getInterval(): float
    {
        return $this->interval;
    }

    public function getStartNum(): ?float
    {
        return $this->startNum;
    }

    public function getEndNum(): ?float
    {
        return $this->endNum;
    }

    /** @return list<string> */
    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function getStartDate(): ?string
    {
        return $this->startDate;
    }

    public function getEndDate(): ?string
    {
        return $this->endDate;
    }
}
