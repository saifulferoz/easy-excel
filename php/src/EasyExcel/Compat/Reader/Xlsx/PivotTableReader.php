<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Reader\Xlsx;

use EasyExcel\Compat\Worksheet\PivotTable\PivotCacheDefinition;
use EasyExcel\Compat\Worksheet\PivotTable\PivotField;
use EasyExcel\Compat\Worksheet\PivotTable\PivotTable;
use EasyExcel\Compat\Worksheet\Worksheet;

/**
 * Reads a pivot table definition (and its cache definition) into the pivot
 * model, PhpSpreadsheet 5.10 style: name, location, source and field layout.
 *
 * Usable directly with SimpleXML loaded in the spreadsheetml main namespace.
 * Loaded Compat workbooks feed it the parts the extension found in the source
 * file (see Worksheet::getPivotTableCollection()). Tables read this way are
 * not "generated", so saving leaves the file's own parts as excelize keeps
 * them, as upstream writes loaded pivots back verbatim.
 */
class PivotTableReader
{
    public function __construct(
        private readonly Worksheet $worksheet,
        private readonly \SimpleXMLElement $pivotTableXml,
        private readonly ?\SimpleXMLElement $cacheDefinitionXml = null,
    ) {
    }

    public function load(): void
    {
        $attrs = $this->pivotTableXml->attributes();
        $pivotTable = new PivotTable((string) ($attrs['name'] ?? ''));
        if (isset($this->pivotTableXml->location)) {
            $pivotTable->setLocation((string) ($this->pivotTableXml->location->attributes()['ref'] ?? ''));
        }

        $cache = $this->readCacheDefinition(isset($attrs['cacheId']) ? (int) $attrs['cacheId'] : null);
        $pivotTable->setCacheDefinition($cache);
        if (isset($this->pivotTableXml->pivotFields)) {
            $this->readFields($pivotTable, $cache);
        }
        $this->worksheet->addPivotTable($pivotTable);
    }

    private function readCacheDefinition(?int $cacheId): PivotCacheDefinition
    {
        $cache = new PivotCacheDefinition($cacheId);
        if ($this->cacheDefinitionXml === null) {
            return $cache;
        }
        $source = $this->cacheDefinitionXml->cacheSource;
        if (isset($source->worksheetSource)) {
            $sourceAttrs = $source->worksheetSource->attributes();
            if (isset($sourceAttrs['sheet'])) {
                $cache->setSourceWorksheet((string) $sourceAttrs['sheet']);
            }
            if (isset($sourceAttrs['ref'])) {
                $cache->setSourceRange((string) $sourceAttrs['ref']);
            }
        }
        if (isset($this->cacheDefinitionXml->cacheFields)) {
            foreach ($this->cacheDefinitionXml->cacheFields->cacheField as $cacheField) {
                $cache->addCacheField((string) ($cacheField->attributes()['name'] ?? ''));
            }
        }

        return $cache;
    }

    private function readFields(PivotTable $pivotTable, PivotCacheDefinition $cache): void
    {
        /** @var array<int, PivotField> $fields */
        $fields = [];
        foreach ($this->pivotTableXml->pivotFields->pivotField as $pivotFieldXml) {
            $index = \count($fields);
            $field = new PivotField($index, $cache->getCacheFieldName($index) ?? '');
            $axis = $pivotFieldXml->attributes()['axis'] ?? null;
            if ($axis !== null) {
                $field->setAxis((string) $axis);
            }
            $fields[$index] = $field;
            $pivotTable->addField($field);
        }

        if (!isset($this->pivotTableXml->dataFields)) {
            return;
        }
        foreach ($this->pivotTableXml->dataFields->dataField as $dataFieldXml) {
            $dataAttrs = $dataFieldXml->attributes();
            $field = isset($dataAttrs['fld']) ? ($fields[(int) $dataAttrs['fld']] ?? null) : null;
            // sum is the OOXML default when the attribute is absent
            $field?->setDataField(true)->setSubtotal(isset($dataAttrs['subtotal']) ? (string) $dataAttrs['subtotal'] : 'sum');
        }
    }
}
