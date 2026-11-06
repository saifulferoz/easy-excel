<?php

declare(strict_types=1);

use EasyExcel\Compat\Cell\CellAddress;
use EasyExcel\Compat\Exception;
use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Compat\Worksheet\Sparkline\SparklineGroup;
use EasyExcel\Compat\Writer\Xlsx;
use EasyExcel\Compat\Worksheet\Sparkline\Sparkline;
use EasyExcel\Compat\Worksheet\Sparkline\SparklineType;

return [
    'sparklines: SparklineType cases carry the OOXML type values' => function (): void {
        T::same('line', SparklineType::Line->value);
        T::same('column', SparklineType::Column->value);
        T::same('stacked', SparklineType::WinLoss->value, 'Win/Loss is "stacked" in OOXML');
        T::same(SparklineType::WinLoss, SparklineType::from('stacked'));
    },

    'sparklines: SparklineType is reachable under the PhpOffice name' => function (): void {
        T::same(
            SparklineType::Column,
            \PhpOffice\PhpSpreadsheet\Worksheet\Sparkline\SparklineType::Column,
            'enum cases are identical through the alias',
        );
    },

    // -------------------------------------------------------------- Sparkline

    'sparklines: Sparkline normalises its location' => function (): void {
        $sp = new Sparkline('Sheet1!$g$2', 'Sheet1!B2:F2');
        T::same('G2', $sp->getLocation(), 'sheet qualifier and $ stripped, uppercased');
        T::same('Sheet1!B2:F2', $sp->getDataRange(), 'data range kept verbatim');
        T::same('G2', (string) $sp);
    },

    'sparklines: Sparkline accepts a CellAddress' => function (): void {
        $sp = new Sparkline(CellAddress::fromCellAddress('H5'), 'B5:F5');
        T::same('H5', $sp->getLocation());
    },

    'sparklines: Sparkline defaults to an empty location' => function (): void {
        $sp = new Sparkline();
        T::same('', $sp->getLocation());
        T::same('', $sp->getDataRange());
        T::ok($sp->setLocation('a1')->setDataRange('B1:C1') === $sp, 'fluent setters');
        T::same('A1', $sp->getLocation());
    },

    'sparklines: Sparkline rejects a range or malformed location' => function (): void {
        T::throws(Exception::class, fn () => new Sparkline('G2:G3', 'B2:F2'), 'range');
        T::throws(Exception::class, fn () => (new Sparkline())->setLocation('not-a-cell'), 'garbage');
    },

    // --------------------------------------------------------- SparklineGroup

    'sparklines: SparklineGroup defaults match PhpSpreadsheet' => function (): void {
        $g = new SparklineGroup();
        T::same(SparklineType::Line, $g->getType());
        T::same([], $g->getSparklines());
        T::same(0.75, $g->getLineWeight());
        T::same(false, $g->getDisplayMarkers());
        T::same(SparklineGroup::EMPTY_AS_GAP, $g->getDisplayEmptyCellsAs());
        T::same(SparklineGroup::AXIS_INDIVIDUAL, $g->getMinAxisType());
        T::same(null, $g->getManualMax());
        T::same('FF376092', $g->getColorSeries());
        T::same('FF000000', $g->getColorAxis());
        T::same('FFD00000', $g->getColorNegative());
        T::same('FFD00000', $g->getColorLow());
        T::same('span', SparklineGroup::EMPTY_AS_SPAN);
        T::same('custom', SparklineGroup::AXIS_CUSTOM);
    },

    'sparklines: SparklineGroup setters round-trip, including unrendered ones' => function (): void {
        $g = (new SparklineGroup())
            ->setType(SparklineType::Column)
            ->setLineWeight(1.5)
            ->setDisplayHigh(true)
            ->setDisplayHidden(true)
            ->setDisplayEmptyCellsAs(SparklineGroup::EMPTY_AS_ZERO)
            ->setMaxAxisType(SparklineGroup::AXIS_CUSTOM)
            ->setManualMax(10.0)
            ->setColorSeries(null)
            ->setColorMarkers('FF00FF00');
        T::same(SparklineType::Column, $g->getType());
        T::same(1.5, $g->getLineWeight());
        T::same(true, $g->getDisplayHigh());
        T::same(true, $g->getDisplayHidden());
        T::same('zero', $g->getDisplayEmptyCellsAs());
        T::same('custom', $g->getMaxAxisType());
        T::same(10.0, $g->getManualMax());
        T::same(null, $g->getColorSeries());
        T::same('FF00FF00', $g->getColorMarkers());
    },

    'sparklines: createSparkline and setSparklines manage members' => function (): void {
        $g = (new SparklineGroup())->createSparkline('G2', 'B2:F2')->createSparkline('G3', 'B3:F3');
        T::same(['G2', 'G3'], \array_map('strval', $g->getSparklines()));
        $g->setSparklines([5 => new Sparkline('H1', 'A1:B1')]);
        T::same(0, \array_key_first($g->getSparklines()), 'reindexed like upstream');
    },

    'sparklines: Worksheet::addSparkline wraps one sparkline in a fresh group' => function (): void {
        $ws = (new Spreadsheet())->getActiveSheet();
        $g = $ws->addSparkline(new Sparkline('G2', 'B2:F2'), SparklineType::WinLoss);
        T::same(SparklineType::WinLoss, $g->getType());
        T::same(1, \count($ws->getSparklineGroupCollection()));
        T::ok($ws->getSparklineGroupCollection()[0] === $g, 'the returned group is the stored one');
        T::ok($ws->addSparklineGroup(new SparklineGroup()) === $ws, 'addSparklineGroup is fluent');
        T::same(2, \count($ws->getSparklineGroupCollection()));
        $ws->removeSparklineGroupCollection();
        T::same(0, \count($ws->getSparklineGroupCollection()));
    },

    'sparklines: nothing is sent until the workbook is saved' => function (): void {
        $s = new Spreadsheet();
        $s->getActiveSheet()->addSparkline(new Sparkline('G2', 'B2:F2'));
        T::same([], EasyExcelFake::calls('add_sparkline_group'), 'adding alone sends nothing');
    },

    'sparklines: Xlsx save sends the group spec, after formatting tweaks' => function (): void {
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $g = $ws->addSparkline(new Sparkline('G2', 'Worksheet!B2:F2'), SparklineType::Column);
        // upstream returns the group precisely so it can be formatted after adding
        $g->createSparkline('G3', 'Worksheet!B3:F3')->setDisplayHigh(true)->setDisplayXAxis(true)
            ->setRightToLeft(true)->setColorSeries('FF00B050');
        $ws->addSparklineGroup(new SparklineGroup()); // empty: skipped

        $path = \tempnam(\sys_get_temp_dir(), 'spark') . '.xlsx';
        try {
            (new Xlsx($s))->save($path);
        } finally {
            @\unlink($path);
        }

        $calls = EasyExcelFake::calls('add_sparkline_group');
        T::same(1, \count($calls), 'one non-empty group');
        T::same('Worksheet', $calls[0][1][1]);
        $spec = $calls[0][1][2];
        T::same('column', $spec['type']);
        T::same([
            ['location' => 'G2', 'dataRange' => 'Worksheet!B2:F2'],
            ['location' => 'G3', 'dataRange' => 'Worksheet!B3:F3'],
        ], $spec['sparklines']);
        T::same(true, $spec['high']);
        T::same(false, $spec['low']);
        T::same(true, $spec['xAxis']);
        T::same(true, $spec['rightToLeft']);
        T::same('FF00B050', $spec['colorSeries']);
        T::ok(EasyExcelFake::calls('add_sparkline_group') !== [] && $g->isWritten(), 'marked written');
    },

    'sparklines: a second save does not re-send written groups' => function (): void {
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->addSparkline(new Sparkline('G2', 'B2:F2'));
        $path = \tempnam(\sys_get_temp_dir(), 'spark') . '.xlsx';
        try {
            (new Xlsx($s))->save($path);
            $ws->addSparkline(new Sparkline('G3', 'B3:F3'));
            (new Xlsx($s))->save($path);
        } finally {
            @\unlink($path);
        }
        $calls = EasyExcelFake::calls('add_sparkline_group');
        T::same(2, \count($calls), 'first group once, second group once');
        T::same('G3', $calls[1][1][2]['sparklines'][0]['location']);
    },

    'sparklines: written groups and sparklines refuse changes' => function (): void {
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $sp = new Sparkline('G2', 'B2:F2');
        $g = $ws->addSparkline($sp);
        $path = \tempnam(\sys_get_temp_dir(), 'spark') . '.xlsx';
        try {
            (new Xlsx($s))->save($path);
        } finally {
            @\unlink($path);
        }
        T::throws(Exception::class, fn () => $g->setDisplayHigh(true), 'group setter');
        T::throws(Exception::class, fn () => $g->createSparkline('G9', 'B9:F9'), 'group membership');
        T::throws(Exception::class, fn () => $sp->setDataRange('C2:F2'), 'member sparkline');
        T::throws(Exception::class, fn () => $ws->removeSparklineGroupCollection(), 'cannot unwrite');
        T::same('B2:F2', $sp->getDataRange(), 'unchanged after the refused write');
    },

    'sparklines: CSV save ignores sparklines' => function (): void {
        $s = new Spreadsheet();
        $s->getActiveSheet()->addSparkline(new Sparkline('G2', 'B2:F2'));
        $path = \tempnam(\sys_get_temp_dir(), 'spark') . '.csv';
        try {
            (new \EasyExcel\Compat\Writer\Csv($s))->save($path);
        } finally {
            @\unlink($path);
        }
        T::same([], EasyExcelFake::calls('add_sparkline_group'));
    },
];
