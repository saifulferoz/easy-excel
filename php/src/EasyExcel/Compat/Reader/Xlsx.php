<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Reader;

use EasyExcel\Compat\Exception;
use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Native;

class Xlsx implements IReader
{
    private bool $readDataOnly = false;

    private string $password = '';

    /** easy-excel extra: opens agile-encrypted workbooks. */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function setReadDataOnly(bool $readDataOnly): static
    {
        // values-only iteration is already the extension's fast path;
        // the flag is accepted for API parity
        $this->readDataOnly = $readDataOnly;

        return $this;
    }

    public function getReadDataOnly(): bool
    {
        return $this->readDataOnly;
    }

    public function canRead(string $filename): bool
    {
        return \is_readable($filename)
            && \in_array(\strtolower(\pathinfo($filename, PATHINFO_EXTENSION)), ['xlsx', 'xlsm', 'xltx', 'xltm'], true);
    }

    private ?IReadFilter $readFilter = null;

    /** Filtered-out cells come back as null from the read APIs (COMPAT.md). */
    public function setReadFilter(IReadFilter $readFilter): static
    {
        $this->readFilter = $readFilter;

        return $this;
    }

    public function getReadFilter(): ?IReadFilter
    {
        return $this->readFilter;
    }

    public function load(string $filename, int $flags = 0): Spreadsheet
    {
        if (!\is_file($filename)) {
            throw new Exception("File \"$filename\" does not exist.");
        }

        $spreadsheet = Spreadsheet::fromHandle(Native::open($filename, $this->password));
        $spreadsheet->setReadFilter($this->readFilter);

        return $spreadsheet;
    }
}
