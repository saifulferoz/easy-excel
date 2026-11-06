<?php

declare(strict_types=1);

use EasyExcel\Compat\Worksheet\PivotTable\PivotCacheDefinition;
use EasyExcel\Compat\Worksheet\PivotTable\PivotField;
use EasyExcel\Compat\Worksheet\PivotTable\PivotFieldGroup;

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
];
