<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet\PivotTable;

use EasyExcel\Compat\Worksheet\Worksheet;

/**
 * A pivot table's metadata, PhpSpreadsheet 5.10 style: name, location on the
 * sheet, source cache and the fields on each axis. Values are not computed
 * here; the spreadsheet application refreshes the pivot when the file opens.
 */
class PivotTable implements \Stringable
{
    private ?Worksheet $worksheet = null;

    /** Range occupied on the sheet, from location@ref, e.g. "A3:D20". */
    private string $location = '';

    private ?PivotCacheDefinition $cacheDefinition = null;

    /** @var list<PivotField> in field-index order */
    private array $fields = [];

    /** True when built in memory (PivotTableBuilder) rather than loaded, so the save writes it. */
    private bool $generated = false;

    public function __construct(private string $name = '')
    {
    }

    public function __destruct()
    {
        $this->worksheet = null;
    }

    public function isGenerated(): bool
    {
        return $this->generated;
    }

    public function setGenerated(bool $generated): self
    {
        $this->generated = $generated;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getWorksheet(): ?Worksheet
    {
        return $this->worksheet;
    }

    public function setWorksheet(?Worksheet $worksheet): self
    {
        $this->worksheet = $worksheet;

        return $this;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getCacheDefinition(): ?PivotCacheDefinition
    {
        return $this->cacheDefinition;
    }

    public function setCacheDefinition(?PivotCacheDefinition $cacheDefinition): self
    {
        $this->cacheDefinition = $cacheDefinition;

        return $this;
    }

    /** @return list<PivotField> */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function addField(PivotField $field): self
    {
        $this->fields[] = $field;

        return $this;
    }

    /** @return list<PivotField> */
    public function getRowFields(): array
    {
        return $this->fieldsOnAxis(PivotField::AXIS_ROW);
    }

    /** @return list<PivotField> */
    public function getColumnFields(): array
    {
        return $this->fieldsOnAxis(PivotField::AXIS_COLUMN);
    }

    /** @return list<PivotField> */
    public function getPageFields(): array
    {
        return $this->fieldsOnAxis(PivotField::AXIS_PAGE);
    }

    /** @return list<PivotField> */
    public function getDataFields(): array
    {
        return \array_values(\array_filter($this->fields, static fn (PivotField $f): bool => $f->isDataField()));
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /** @return list<PivotField> */
    private function fieldsOnAxis(string $axis): array
    {
        return \array_values(\array_filter($this->fields, static fn (PivotField $f): bool => $f->getAxis() === $axis));
    }
}
