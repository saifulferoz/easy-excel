<?php

declare(strict_types=1);

use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Compat\Writer\Xlsx;

/** The precalculate flag the writer sent to the extension, or null. */
function fcSentFlag(): ?bool
{
    $calls = EasyExcelFake::calls('set_precalculate_formulas');

    return $calls === [] ? null : $calls[\count($calls) - 1][1][1];
}

return [
    'formula cache: the writer defaults to pre-calculating, like PhpSpreadsheet' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $w = new Xlsx($s);
        T::same(true, $w->getPreCalculateFormulas(), 'PhpSpreadsheet default');

        $w->save(\tempnam(\sys_get_temp_dir(), 'fc') . '.xlsx');
        T::same(true, fcSentFlag(), 'the default reaches the extension');
    },

    'formula cache: setPreCalculateFormulas(false) reaches the extension' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $w = new Xlsx($s);
        $w->setPreCalculateFormulas(false);

        $w->save(\tempnam(\sys_get_temp_dir(), 'fc') . '.xlsx');
        T::same(false, fcSentFlag(), 'opting out is honoured, not ignored');
    },

    'formula cache: the flag is no longer state-only' => function (): void {
        // Before this change the accessor round-tripped but nothing consumed
        // it (COMPAT.md called it "state-only"). Pin that it is wired now.
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        (new Xlsx($s))->save(\tempnam(\sys_get_temp_dir(), 'fc') . '.xlsx');
        T::same(
            1,
            \count(EasyExcelFake::calls('set_precalculate_formulas')),
            'saving must tell the extension what to do about formulas',
        );
    },

    'formula cache: DISABLE_PRECALCULATE_FORMULAE flag turns it off' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $w = new Xlsx($s);
        $w->save(\tempnam(\sys_get_temp_dir(), 'fc') . '.xlsx', Xlsx::DISABLE_PRECALCULATE_FORMULAE);
        T::same(false, fcSentFlag(), 'the save flag maps through to the extension');
    },

    'formula cache: the accessor still round-trips both ways' => function (): void {
        EasyExcelFake::reset();
        $w = new Xlsx(new Spreadsheet());
        T::ok($w->setPreCalculateFormulas(false) === $w, 'fluent');
        T::same(false, $w->getPreCalculateFormulas());
        $w->setPreCalculateFormulas(true);
        T::same(true, $w->getPreCalculateFormulas());
    },

    'formula cache: each save re-sends the current flag' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        $w = new Xlsx($s);
        $path = \tempnam(\sys_get_temp_dir(), 'fc') . '.xlsx';

        $w->save($path);
        T::same(true, fcSentFlag(), 'first save: on');

        $w->setPreCalculateFormulas(false);
        $w->save($path);
        T::same(false, fcSentFlag(), 'second save reflects the change');

        T::same(2, \count(EasyExcelFake::calls('set_precalculate_formulas')), 'sent per save');
    },

    'formula cache: Native exposes the fullCalcOnLoad control' => function (): void {
        EasyExcelFake::reset();
        $s = new Spreadsheet();
        \EasyExcel\Native::setFullCalcOnLoad($s->getHandle(), false);
        $calls = EasyExcelFake::calls('set_full_calc_on_load');
        T::same(1, \count($calls));
        T::same(false, $calls[0][1][1]);
    },
];
