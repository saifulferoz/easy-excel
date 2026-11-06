<?php

declare(strict_types=1);

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
];
