<?php

declare(strict_types=1);

use EasyExcel\Compat\Worksheet\PivotTable\PivotCacheDefinition;
use EasyExcel\Compat\Worksheet\PivotTable\PivotField;
use EasyExcel\Compat\Exception;
use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Compat\Worksheet\PivotTable\PivotFieldGroup;
use EasyExcel\Compat\Worksheet\PivotTable\PivotTable;
use EasyExcel\Compat\Worksheet\PivotTable\PivotTableBuilder;
use EasyExcel\Compat\Writer\Xlsx;

/** A Data sheet with a header row and four sales rows, plus an empty Report sheet. */
function pivotSalesWorkbook(): Spreadsheet
{
    $s = new Spreadsheet();
    $data = $s->getActiveSheet()->setTitle('Data');
    $data->fromArray([
        ['Region', 'Product', 'Amount'],
        ['East', 'Pens', 10],
        ['West', 'Pens', 20],
        ['East', 'Ink', 5],
        ['', 'Ink', 7],
    ]);
    $s->createSheet()->setTitle('Report');

    return $s;
}

function pivotSave(Spreadsheet $s): void
{
    $path = \tempnam(\sys_get_temp_dir(), 'pivot') . '.xlsx';
    try {
        (new Xlsx($s))->save($path);
    } finally {
        @\unlink($path);
    }
}

return [
    // -------------------------------------------------------- PivotFieldGroup

    'pivot: PivotFieldGroup::numeric carries the bucket range' => function (): void {
        $g = PivotFieldGroup::numeric(100.0, 0.0, 1000.0);
        T::same(PivotFieldGroup::TYPE_NUMERIC, $g->getType());
        T::ok($g->isNumeric() && !$g->isDate(), 'numeric');
        T::same(100.0, $g->getInterval());
        T::same(0.0, $g->getStartNum(), 'an explicit 0 is not unset');
        T::same(1000.0, $g->getEndNum());
        T::same([], $g->getGroupBy());
    },

    'pivot: PivotFieldGroup::date normalises groupBy to a list' => function (): void {
        $one = PivotFieldGroup::date(PivotFieldGroup::GROUP_BY_MONTHS);
        T::same(['months'], $one->getGroupBy());
        T::same(null, $one->getStartDate());
        T::same(1.0, $one->getInterval(), 'interval keeps its default');

        $many = PivotFieldGroup::date(
            [3 => PivotFieldGroup::GROUP_BY_YEARS, 7 => PivotFieldGroup::GROUP_BY_QUARTERS],
            '2026-01-01',
            '2026-12-31',
        );
        T::ok($many->isDate() && !$many->isNumeric(), 'date');
        T::same(['years', 'quarters'], $many->getGroupBy(), 'reindexed');
        T::same('2026-01-01', $many->getStartDate());
        T::same('2026-12-31', $many->getEndDate());
    },

    'pivot: PivotFieldGroup GROUP_BY_* match rangePr@groupBy' => function (): void {
        T::same(
            ['seconds', 'minutes', 'hours', 'days', 'months', 'quarters', 'years'],
            [
                PivotFieldGroup::GROUP_BY_SECONDS, PivotFieldGroup::GROUP_BY_MINUTES,
                PivotFieldGroup::GROUP_BY_HOURS, PivotFieldGroup::GROUP_BY_DAYS,
                PivotFieldGroup::GROUP_BY_MONTHS, PivotFieldGroup::GROUP_BY_QUARTERS,
                PivotFieldGroup::GROUP_BY_YEARS,
            ],
        );
    },

    'pivot: PivotFieldGroup has no public constructor' => function (): void {
        T::ok(!(new \ReflectionMethod(PivotFieldGroup::class, '__construct'))->isPublic(), 'factories only');
    },

    // ------------------------------------------------------------- PivotField

    'pivot: PivotField starts unplaced and not a value field' => function (): void {
        $f = new PivotField(2, 'Region');
        T::same(2, $f->getIndex());
        T::same('Region', $f->getName());
        T::same(PivotField::AXIS_NONE, $f->getAxis());
        T::same(false, $f->isDataField());
        T::same(null, $f->getSubtotal());
        T::same(null, $f->getDataFieldCaption());
        T::same('', (new PivotField(0))->getName(), 'name defaults to empty');
    },

    'pivot: PivotField setters round-trip and are fluent' => function (): void {
        $f = new PivotField(1);
        $same = $f->setName('Amount')->setAxis(PivotField::AXIS_VALUES)->setDataField(true)
            ->setSubtotal(PivotField::SUBTOTAL_AVERAGE)->setDataFieldCaption('Average of Amount');
        T::ok($same === $f, 'fluent');
        T::same('Amount', $f->getName());
        T::same('axisValues', $f->getAxis());
        T::same(true, $f->isDataField());
        T::same('average', $f->getSubtotal());
        T::same('Average of Amount', $f->getDataFieldCaption());
        $f->setSubtotal(null)->setDataFieldCaption(null);
        T::same(null, $f->getSubtotal());
    },

    'pivot: PivotField constants match the OOXML attribute values' => function (): void {
        T::same(['axisRow', 'axisCol', 'axisPage'], [PivotField::AXIS_ROW, PivotField::AXIS_COLUMN, PivotField::AXIS_PAGE]);
        T::same(
            ['sum', 'count', 'average', 'max', 'min', 'product', 'countNums', 'stdDev', 'stdDevp', 'var', 'varp'],
            [
                PivotField::SUBTOTAL_SUM, PivotField::SUBTOTAL_COUNT, PivotField::SUBTOTAL_AVERAGE,
                PivotField::SUBTOTAL_MAX, PivotField::SUBTOTAL_MIN, PivotField::SUBTOTAL_PRODUCT,
                PivotField::SUBTOTAL_COUNT_NUMS, PivotField::SUBTOTAL_STD_DEV, PivotField::SUBTOTAL_STD_DEV_P,
                PivotField::SUBTOTAL_VAR, PivotField::SUBTOTAL_VAR_P,
            ],
        );
    },

    // --------------------------------------------------- PivotCacheDefinition

    'pivot: PivotCacheDefinition starts empty' => function (): void {
        $c = new PivotCacheDefinition();
        T::same(null, $c->getCacheId());
        T::same(null, $c->getSourceWorksheet());
        T::same(null, $c->getSourceRange());
        T::same([], $c->getCacheFields());
        T::same(null, $c->getCacheFieldName(0));
        T::same([], $c->getSharedItems('Region'), 'unknown field has no items');
        T::same(null, $c->getFieldGroup('Region'));
        T::same(4, (new PivotCacheDefinition(4))->getCacheId());
    },

    'pivot: PivotCacheDefinition source and fields round-trip' => function (): void {
        $c = (new PivotCacheDefinition())->setCacheId(1)->setSourceWorksheet('Data')->setSourceRange('A1:C9')
            ->addCacheField('Region')->addCacheField('Month');
        T::same(1, $c->getCacheId());
        T::same('Data', $c->getSourceWorksheet());
        T::same('A1:C9', $c->getSourceRange());
        T::same(['Region', 'Month'], $c->getCacheFields());
        T::same('Month', $c->getCacheFieldName(1), 'index is declaration order');

        $c->setCacheFields([2 => 'A', 9 => 'B']);
        T::same(['A', 'B'], $c->getCacheFields(), 'reindexed');
        $c->setSourceRange(null)->setCacheId(null);
        T::same(null, $c->getSourceRange());
    },

    'pivot: PivotCacheDefinition shared items and groups are per field' => function (): void {
        $group = PivotFieldGroup::numeric(10.0);
        $c = (new PivotCacheDefinition())
            ->setSharedItems('Region', [4 => 'East', 8 => 'West'])
            ->setFieldGroup('Amount', $group);
        T::same(['East', 'West'], $c->getSharedItems('Region'), 'reindexed');
        T::same([], $c->getSharedItems('Amount'));
        T::ok($c->getFieldGroup('Amount') === $group, 'same group instance');
        T::same(null, $c->getFieldGroup('Region'));
    },

    // ------------------------------------------------------------- PivotTable

    'pivot: PivotTable defaults' => function (): void {
        $t = new PivotTable('Sales');
        T::same('Sales', $t->getName());
        T::same('Sales', (string) $t);
        T::same(false, $t->isGenerated());
        T::same(null, $t->getWorksheet());
        T::same('', $t->getLocation());
        T::same(null, $t->getCacheDefinition());
        T::same([], $t->getFields());
        T::same('', (new PivotTable())->getName());
    },

    'pivot: PivotTable splits fields by axis, in order' => function (): void {
        $t = (new PivotTable('P'))
            ->addField((new PivotField(0, 'Region'))->setAxis(PivotField::AXIS_ROW))
            ->addField((new PivotField(1, 'Product'))->setAxis(PivotField::AXIS_COLUMN))
            ->addField((new PivotField(2, 'Year'))->setAxis(PivotField::AXIS_PAGE))
            ->addField((new PivotField(3, 'Amount'))->setDataField(true)->setSubtotal('sum'))
            ->addField((new PivotField(4, 'Month'))->setAxis(PivotField::AXIS_ROW))
            ->addField(new PivotField(5, 'Unused'));
        $names = static fn (array $fields): array => \array_map(static fn (PivotField $f): string => $f->getName(), $fields);
        T::same(['Region', 'Month'], $names($t->getRowFields()));
        T::same(['Product'], $names($t->getColumnFields()));
        T::same(['Year'], $names($t->getPageFields()));
        T::same(['Amount'], $names($t->getDataFields()));
        T::same(6, \count($t->getFields()));
        T::same([0], \array_keys($t->getColumnFields()), 'axis lists are reindexed');
    },

    'pivot: PivotTable setters round-trip' => function (): void {
        $cache = new PivotCacheDefinition(1);
        $t = (new PivotTable())->setName('Q')->setGenerated(true)->setLocation('A3:A3')->setCacheDefinition($cache);
        T::same('Q', $t->getName());
        T::same(true, $t->isGenerated());
        T::same('A3:A3', $t->getLocation());
        T::ok($t->getCacheDefinition() === $cache, 'same cache');
    },

    'pivot: Worksheet pivot table collection' => function (): void {
        $ws = (new Spreadsheet())->getActiveSheet();
        T::same(0, \count($ws->getPivotTableCollection()));
        $a = new PivotTable('Sales');
        T::ok($ws->addPivotTable($a) === $ws, 'fluent');
        $ws->addPivotTable(new PivotTable('Costs'));
        T::ok($a->getWorksheet() === $ws, 'addPivotTable binds the worksheet');
        T::ok($ws->getPivotTables() === $ws->getPivotTableCollection(), 'alias returns the same collection');
        T::same(['Sales', 'Costs'], $ws->getPivotTableNames());
        T::ok($ws->getPivotTableByName('sALES') === $a, 'lookup is case-insensitive');
        T::same(null, $ws->getPivotTableByName('Nope'));
        $ws->removePivotTableCollection();
        T::same([], $ws->getPivotTableNames());
    },

    // ------------------------------------------------------ PivotTableBuilder

    'pivot: builder reads field names from the header row' => function (): void {
        $s = pivotSalesWorkbook();
        $b = new PivotTableBuilder($s->getSheetByName('Data'), '$A$1:$C$5');
        $t = $b->addRowField('Region')->addDataField('Amount')->build($s->getSheetByName('Report'), '$B$3');
        T::same(['Region', 'Product', 'Amount'], $t->getCacheDefinition()->getCacheFields());
        T::same('A1:C5', $t->getCacheDefinition()->getSourceRange(), '$ markers stripped');
        T::same('Data', $t->getCacheDefinition()->getSourceWorksheet());
        T::same(1, $t->getCacheDefinition()->getCacheId());
        T::same('B3:B3', $t->getLocation(), 'degenerate one-cell location, like upstream');
        T::same('PivotTable1', $t->getName(), 'default name');
        T::ok($t->isGenerated(), 'built tables are generated');
    },

    'pivot: builder places fields and derives captions' => function (): void {
        $s = pivotSalesWorkbook();
        $t = (new PivotTableBuilder($s->getSheetByName('Data'), 'A1:C5'))
            ->addRowField('Region')
            ->addColumnField('Product')
            ->addDataField('Amount', PivotField::SUBTOTAL_COUNT_NUMS)
            ->build($s->getSheetByName('Report'), 'A3', 'Sales');
        T::same('Sales', $t->getName());
        T::same('Region', $t->getRowFields()[0]->getName());
        T::same('Product', $t->getColumnFields()[0]->getName());
        $amount = $t->getDataFields()[0];
        T::same(2, $amount->getIndex(), 'fields keep source-column order');
        T::same('countNums', $amount->getSubtotal());
        T::same('Count of Amount', $amount->getDataFieldCaption(), 'countNums is captioned Count, like upstream');
        T::same(PivotField::AXIS_NONE, $amount->getAxis(), 'value fields carry no axis');
        T::same(3, \count($t->getFields()));

        $custom = (new PivotTableBuilder($s->getSheetByName('Data'), 'A1:C5'))
            ->addDataField('Amount', PivotField::SUBTOTAL_MAX, 'Biggest')
            ->build($s->getSheetByName('Report'), 'H3', 'Max');
        T::same('Biggest', $custom->getDataFields()[0]->getDataFieldCaption());
    },

    'pivot: builder records distinct values and groups on the cache' => function (): void {
        $s = pivotSalesWorkbook();
        $t = (new PivotTableBuilder($s->getSheetByName('Data'), 'A1:C5'))
            ->addRowField('Region')
            ->groupFieldByNumericRange('Amount', 10.0, 0.0)
            ->addDataField('Amount')
            ->build($s->getSheetByName('Report'), 'A3');
        $cache = $t->getCacheDefinition();
        T::same(['East', 'West'], $cache->getSharedItems('Region'), 'first-seen order, blanks skipped');
        T::same(['10', '20', '5', '7'], $cache->getSharedItems('Amount'), 'values are strings');
        T::same(10.0, $cache->getFieldGroup('Amount')->getInterval());
        T::same(null, $cache->getFieldGroup('Region'));
    },

    'pivot: builder registers the table on the target sheet' => function (): void {
        $s = pivotSalesWorkbook();
        $report = $s->getSheetByName('Report');
        $t = (new PivotTableBuilder($s->getSheetByName('Data'), 'A1:C5'))->addDataField('Amount')->build($report, 'A3');
        T::ok($report->getPivotTableByName('PivotTable1') === $t, 'registered');
        T::ok($t->getWorksheet() === $report, 'bound to the target');
        T::same([], $s->getSheetByName('Data')->getPivotTableNames(), 'not on the source sheet');
    },

    'pivot: builder rejects unknown fields and a missing data field' => function (): void {
        $s = pivotSalesWorkbook();
        $b = new PivotTableBuilder($s->getSheetByName('Data'), 'A1:C5');
        T::throws(Exception::class, fn () => $b->addRowField('Nope'), 'unknown row field');
        T::throws(Exception::class, fn () => $b->groupFieldByDate('Nope', PivotFieldGroup::GROUP_BY_YEARS), 'unknown group field');
        $b->addRowField('Region');
        T::throws(Exception::class, fn () => $b->build($s->getSheetByName('Report'), 'A3'), 'no data field');
    },

    // ------------------------------------------------- writing generated pivots

    'pivot: nothing is sent until save, then the spec reaches the extension' => function (): void {
        $s = pivotSalesWorkbook();
        (new PivotTableBuilder($s->getSheetByName('Data'), 'A1:C5'))
            ->addRowField('Region')->addColumnField('Product')->addPageField('Amount')
            ->addDataField('Amount', PivotField::SUBTOTAL_AVERAGE)
            ->build($s->getSheetByName('Report'), 'A3', 'Sales');
        T::same([], EasyExcelFake::calls('set_pivot_tables'), 'build alone sends nothing');

        pivotSave($s);
        $calls = EasyExcelFake::calls('set_pivot_tables');
        T::same(1, \count($calls), 'only the sheet with pivots is synced');
        T::same('Report', $calls[0][1][1]);
        T::same([[
            'name' => 'Sales',
            'sourceSheet' => 'Data',
            'sourceRange' => 'A1:C5',
            'location' => 'A3:A3',
            'rows' => [['field' => 'Region']],
            'columns' => [['field' => 'Product']],
            'filters' => [],
            'data' => [['field' => 'Amount', 'subtotal' => 'average', 'caption' => 'Average of Amount']],
        ]], $calls[0][1][2], 'placing Amount as a value overrides its page placement, like upstream');
    },

    'pivot: every save resends the full set, and clearing it sends an empty list' => function (): void {
        $s = pivotSalesWorkbook();
        $report = $s->getSheetByName('Report');
        (new PivotTableBuilder($s->getSheetByName('Data'), 'A1:C5'))->addDataField('Amount')->build($report, 'A3', 'A');
        pivotSave($s);
        $report->getPivotTableByName('A')->setName('Renamed');
        pivotSave($s);
        $report->removePivotTableCollection();
        pivotSave($s);
        pivotSave($s);

        $calls = EasyExcelFake::calls('set_pivot_tables');
        T::same(3, \count($calls), 'two saves with tables, one clearing call, then nothing');
        T::same('Renamed', $calls[1][1][2][0]['name'], 'later edits reach the next save');
        T::same([], $calls[2][1][2], 'clearing replaces the written set with nothing');
    },

    'pivot: loaded (non-generated) tables are not sent' => function (): void {
        $s = pivotSalesWorkbook();
        $s->getSheetByName('Report')->addPivotTable(new PivotTable('FromFile'));
        pivotSave($s);
        T::same([], EasyExcelFake::calls('set_pivot_tables'));
    },

    'pivot: a generated table without a cache definition fails the save' => function (): void {
        $s = pivotSalesWorkbook();
        $s->getSheetByName('Report')->addPivotTable((new PivotTable('Broken'))->setGenerated(true));
        T::throws(Exception::class, fn () => pivotSave($s));
    },
];
