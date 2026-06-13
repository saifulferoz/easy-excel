<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Writer;

use EasyExcel\Compat\Exception;
use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Native;

class Xlsx
{
    private string $password = '';

    public function __construct(private Spreadsheet $spreadsheet)
    {
    }

    /**
     * easy-excel extra (PhpSpreadsheet cannot write encrypted xlsx): a
     * non-empty password produces an agile-encrypted container. Encryption
     * routes streamed auto-filters through the save-time degrade.
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Saves to a filesystem path or a php:// stream. Streams go through a
     * temp file because the extension writes files directly (the xlsx
     * container is already deflated — never double-compress it, PLAN.md B10).
     */
    public function save(string $filename, int $flags = 0): void
    {
        $this->spreadsheet->flushAll();
        $handle = $this->spreadsheet->getHandle();

        if (!\str_starts_with($filename, 'php://')) {
            Native::saveXlsx($handle, $filename, $this->password);

            return;
        }

        $tmp = \tempnam(\sys_get_temp_dir(), 'eexcel');
        if ($tmp === false) {
            throw new Exception('Could not create temporary file');
        }
        try {
            Native::saveXlsx($handle, $tmp, $this->password);
            $out = \fopen($filename, 'wb');
            if ($out === false) {
                throw new Exception("Could not open $filename for writing");
            }
            $in = \fopen($tmp, 'rb');
            \stream_copy_to_stream($in, $out);
            \fclose($in);
            \fclose($out);
        } finally {
            @\unlink($tmp);
        }
    }
}
