<?php

declare(strict_types=1);

use EasyExcel\Compat\Cell\DataValidation;
use EasyExcel\Compat\Reader\IReadFilter;
use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Compat\Style\Conditional;

return [
    'iterators: rows and cells walk the used range' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->fromArray([['a', 'b'], ['c', 'd'], ['e', 'f']]);

        $seen = [];
        foreach ($ws->getRowIterator() as $rowNum => $row) {
            foreach ($row->getCellIterator() as $colLetter => $cell) {
                $seen[$colLetter . $rowNum] = $cell->getValue();
            }
        }
        T::same(['A1' => 'a', 'B1' => 'b', 'A2' => 'c', 'B2' => 'd', 'A3' => 'e', 'B3' => 'f'], $seen);
    },

    'iterators: column iterator with bounds' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->fromArray([[1, 2, 3], [4, 5, 6]]);

        $cols = [];
        foreach ($ws->getColumnIterator('B') as $letter => $column) {
            $values = [];
            foreach ($column->getCellIterator() as $cell) {
                $values[] = $cell->getValue();
            }
            $cols[$letter] = $values;
        }
        T::same(['B' => [2.0, 5.0], 'C' => [3.0, 6.0]], $cols);
    },

    'read filter: filtered cells come back null' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->fromArray([['keep', 'drop'], ['keep2', 'drop2']]);
        $s->setReadFilter(new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $columnAddress === 'A';
            }
        });

        T::same([['keep', null], ['keep2', null]], $ws->toArray());
        T::same('keep', $ws->getCell('A1')->getValue());
        T::same(null, $ws->getCell('B1')->getValue());
        $s->setReadFilter(null);
    },

    'style read-back: getters reflect applied styles' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->getStyle('B2:B5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => 'center'],
            'numberFormat' => ['formatCode' => '0.00%'],
        ]);

        $style = $ws->getStyle('B2:B5'); // fresh instance: must read back
        T::same(true, $style->getFont()->getBold());
        T::same(14.0, $style->getFont()->getSize());
        T::same('Arial', $style->getFont()->getName());
        T::same('center', $style->getAlignment()->getHorizontal());
        T::same('0.00%', $style->getNumberFormat()->getFormatCode());
        // untouched property falls back to the default
        T::same(false, $style->getFont()->getItalic());
    },

    'duplicateStyle: copies an attached style onto a range' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->getStyle('A1')->getFont()->setBold(true);
        $ws->duplicateStyle($ws->getStyle('A1'), 'C3:D4');

        $calls = EasyExcelFake::calls('apply_style');
        $last = \end($calls)[1];
        T::same('C3:D4', $last[2]);
        T::same(true, $last[3]['font']['bold']);
    },

    'default style: accumulates and pushes to the workbook' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $s->getDefaultStyle()->getFont()->setName('Arial');
        $s->getDefaultStyle()->getFont()->setSize(9);

        $calls = EasyExcelFake::calls('set_default_style');
        T::same(2, \count($calls));
        // size survives the JSON round trip as an int
        T::same(['font' => ['name' => 'Arial', 'size' => 9]], \end($calls)[1][1]);
        // and the default layers under cell read-back
        T::same('Arial', $s->getActiveSheet()->getStyle('Q9')->getFont()->getName());
    },

    'validation read-back: getDataValidation hydrates covering rules' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $v = new DataValidation();
        $v->setType(DataValidation::TYPE_LIST);
        $v->setFormula1('"a,b,c"');
        $v->setAllowBlank(true);
        $ws->setDataValidation('C2:C9', $v);

        $got = $ws->getCell('C5')->getDataValidation();
        T::same(DataValidation::TYPE_LIST, $got->getType());
        T::same('"a,b,c"', $got->getFormula1());
        T::same(true, $got->getAllowBlank());
        // a cell outside the range gets a fresh rule
        T::same(DataValidation::TYPE_NONE, $ws->getCell('D5')->getDataValidation()->getType());
    },

    'conditional read-back: native fallback hydrates rules' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $rule = (new Conditional())->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)->addCondition('5');
        $rule->getStyle()->getFont()->setBold(true);
        $ws->getStyle('B2:B9')->setConditionalStyles([$rule]);
        // wipe the session registry to force the native path
        $ws->rememberConditionalStyles('B2:B9', []);

        $got = $ws->getStyle('B2:B9')->getConditionalStyles();
        T::same(1, \count($got));
        T::same(Conditional::CONDITION_CELLIS, $got[0]->getConditionType());
        T::same(Conditional::OPERATOR_GREATERTHAN, $got[0]->getOperatorType());
        T::same(['5'], $got[0]->getConditions());
    },

    'defined names: getter returns NamedRange objects' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $s->addNamedRange(new \EasyExcel\Compat\NamedRange('data', $s->getActiveSheet(), '$A$1:$B$2'));

        $names = $s->getDefinedNames();
        T::ok(isset($names['DATA']), 'keyed by upper-case name');
        T::same('data', $names['DATA']->getName());
        T::same('Worksheet!$A$1:$B$2', $names['DATA']->getRange());
    },

    'auto filter: object reflects the session range' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $ws = $s->getActiveSheet();
        $ws->setAutoFilter('A1:F100');
        T::same('A1:F100', $ws->getAutoFilter()->getRange());
        $ws->getAutoFilter()->setRange('A1:G200');
        T::same('A1:G200', $ws->getAutoFilter()->getRange());
        T::same(2, \count(EasyExcelFake::calls('auto_filter')));
    },
];
