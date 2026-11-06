<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Worksheet\PivotTable;

/**
 * Where a pivot table's data comes from, PhpSpreadsheet 5.10 style: the source
 * worksheet and range, the cache field names (whose order gives each
 * PivotField its index), distinct values and per-field grouping.
 */
class PivotCacheDefinition
{
    private ?string $sourceWorksheet = null;

    /** Source range on that worksheet, e.g. "A1:D50". */
    private ?string $sourceRange = null;

    /** @var list<string> */
    private array $cacheFields = [];

    /** @var array<string, list<string>> distinct values per field name */
    private array $sharedItems = [];

    /** @var array<string, PivotFieldGroup> */
    private array $fieldGroups = [];

    /** @param ?int $cacheId the id registered in the workbook's pivotCaches */
    public function __construct(private ?int $cacheId = null)
    {
    }

    public function getCacheId(): ?int
    {
        return $this->cacheId;
    }

    public function setCacheId(?int $cacheId): self
    {
        $this->cacheId = $cacheId;

        return $this;
    }

    public function getSourceWorksheet(): ?string
    {
        return $this->sourceWorksheet;
    }

    public function setSourceWorksheet(?string $sourceWorksheet): self
    {
        $this->sourceWorksheet = $sourceWorksheet;

        return $this;
    }

    public function getSourceRange(): ?string
    {
        return $this->sourceRange;
    }

    public function setSourceRange(?string $sourceRange): self
    {
        $this->sourceRange = $sourceRange;

        return $this;
    }

    /** @return list<string> */
    public function getCacheFields(): array
    {
        return $this->cacheFields;
    }

    public function addCacheField(string $name): self
    {
        $this->cacheFields[] = $name;

        return $this;
    }

    /** @param array<string> $cacheFields */
    public function setCacheFields(array $cacheFields): self
    {
        $this->cacheFields = \array_values($cacheFields);

        return $this;
    }

    public function getCacheFieldName(int $index): ?string
    {
        return $this->cacheFields[$index] ?? null;
    }

    /** @param array<string> $items */
    public function setSharedItems(string $fieldName, array $items): self
    {
        $this->sharedItems[$fieldName] = \array_values($items);

        return $this;
    }

    /** @return list<string> */
    public function getSharedItems(string $fieldName): array
    {
        return $this->sharedItems[$fieldName] ?? [];
    }

    public function setFieldGroup(string $fieldName, PivotFieldGroup $group): self
    {
        $this->fieldGroups[$fieldName] = $group;

        return $this;
    }

    public function getFieldGroup(string $fieldName): ?PivotFieldGroup
    {
        return $this->fieldGroups[$fieldName] ?? null;
    }
}
