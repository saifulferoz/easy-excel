<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Reader;

use EasyExcel\Compat\Exception;

/**
 * PhpSpreadsheet 5.6+ CSV reader that never applies an escape character and
 * never auto-detects the delimiter/enclosure, giving strict RFC-4180 parsing.
 *
 * It is an extendable variant of {@see Csv}: it forbids re-enabling escaping or
 * auto-detection so subclasses can rely on the no-escape contract. The escape
 * character is pinned to '' at construction, matching upstream.
 */
class CsvNoEscape extends Csv
{
    public function __construct()
    {
        $this->escapeCharacter = '';
        $this->testAutodetect = false;
    }

    public function setEscapeCharacter(string $escapeCharacter, int $version = \PHP_VERSION_ID): static
    {
        if ($escapeCharacter !== '') {
            throw new Exception('Escape character must be null string');
        }

        $this->escapeCharacter = $escapeCharacter;

        return $this;
    }

    public function setTestAutoDetect(bool $value): static
    {
        if ($value !== false) {
            throw new Exception('This class requires that testAutoDetect be false');
        }

        $this->testAutodetect = $value;

        return $this;
    }
}
