<?php

declare(strict_types=1);

namespace EasyExcel\Compat;

use EasyExcel\Compat\Reader\Csv as CsvReader;
use EasyExcel\Compat\Reader\Exception as ReaderException;
use EasyExcel\Compat\Reader\IReader;
use EasyExcel\Compat\Reader\Xlsx as XlsxReader;
use EasyExcel\Compat\Writer\Csv as CsvWriter;
use EasyExcel\Compat\Writer\Exception as WriterException;
use EasyExcel\Compat\Writer\Html as HtmlWriter;
use EasyExcel\Compat\Writer\IWriter;
use EasyExcel\Compat\Writer\Xlsx as XlsxWriter;

abstract class IOFactory
{
    public const READER_XLSX = 'Xlsx';
    public const READER_CSV = 'Csv';
    public const WRITER_XLSX = 'Xlsx';
    public const WRITER_CSV = 'Csv';
    public const WRITER_HTML = 'Html';

    /**
     * User-registered writers, consulted before the built-ins so a format
     * (e.g. 'Html') can be overridden and new ones (e.g. 'Pdf') added.
     *
     * @var array<string, class-string<IWriter>>
     */
    private static array $registeredWriters = [];

    /** @var array<string, class-string<IReader>> */
    private static array $registeredReaders = [];

    /** @param class-string<IWriter> $writerClass */
    public static function registerWriter(string $writerType, string $writerClass): void
    {
        if (!\is_a($writerClass, IWriter::class, true)) {
            throw new WriterException('Registered writers must implement ' . IWriter::class);
        }
        self::$registeredWriters[$writerType] = $writerClass;
    }

    /** @param class-string<IReader> $readerClass */
    public static function registerReader(string $readerType, string $readerClass): void
    {
        if (!\is_a($readerClass, IReader::class, true)) {
            throw new ReaderException('Registered readers must implement ' . IReader::class);
        }
        self::$registeredReaders[$readerType] = $readerClass;
    }

    public static function createWriter(Spreadsheet $spreadsheet, string $writerType): IWriter
    {
        if (isset(self::$registeredWriters[$writerType])) {
            $writerClass = self::$registeredWriters[$writerType];

            return new $writerClass($spreadsheet);
        }

        return match ($writerType) {
            self::WRITER_XLSX => new XlsxWriter($spreadsheet),
            self::WRITER_CSV => new CsvWriter($spreadsheet),
            self::WRITER_HTML => new HtmlWriter($spreadsheet),
            default => throw new Exception(
                "easy-excel: writer \"$writerType\" is not supported yet (COMPAT.md lists supported formats)"
            ),
        };
    }

    public static function createReader(string $readerType): IReader
    {
        if (isset(self::$registeredReaders[$readerType])) {
            $readerClass = self::$registeredReaders[$readerType];

            return new $readerClass();
        }

        return match ($readerType) {
            self::READER_XLSX => new XlsxReader(),
            self::READER_CSV => new CsvReader(),
            default => throw new Exception(
                "easy-excel: reader \"$readerType\" is not supported yet (COMPAT.md lists supported formats)"
            ),
        };
    }

    /**
     * Extension-based identification first (cheap, covers the built-ins);
     * unknown extensions fall back to canRead() probing so registered readers
     * get a chance. Registered readers probe before built-ins, and Csv probes
     * last since its canRead() accepts any readable file.
     *
     * @param string[]|null $readers restrict to these reader types (as in PhpSpreadsheet)
     */
    public static function createReaderForFile(string $filename, ?array $readers = null): IReader
    {
        $probeTypes = [
            ...\array_keys(self::$registeredReaders),
            self::READER_XLSX,
            self::READER_CSV,
        ];
        if ($readers !== null) {
            $probeTypes = \array_values(\array_intersect($probeTypes, $readers));
        }

        try {
            $type = self::identify($filename);
            if ($readers === null || \in_array($type, $readers, true)) {
                $reader = self::createReader($type);
                if ($reader->canRead($filename)) {
                    return $reader;
                }
            }
        } catch (Exception) {
            // unknown extension — fall through to probing
        }

        foreach (\array_unique($probeTypes) as $type) {
            $reader = self::createReader($type);
            if ($reader->canRead($filename)) {
                return $reader;
            }
        }

        throw new ReaderException("Unable to identify a reader for this file: $filename");
    }

    public static function load(string $filename, int $flags = 0, ?array $readers = null): Spreadsheet
    {
        return self::createReaderForFile($filename, $readers)->load($filename, $flags);
    }

    public static function identify(string $filename, ?array $readers = null): string
    {
        $ext = \strtolower(\pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx', 'xlsm', 'xltx', 'xltm' => self::READER_XLSX,
            'csv', 'tsv' => self::READER_CSV,
            default => throw new Exception("Unable to identify a reader for this file: $filename"),
        };
    }
}
