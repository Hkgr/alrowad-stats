<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal read-only XLSX reader (shared strings + inline strings + plain values).
 * Enough for reading names out of the institution's reference workbooks without a
 * spreadsheet dependency. Formulas are read as their cached values.
 */
final class XlsxReader
{
    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private ZipArchive $zip;

    /** @var list<string> */
    private array $sharedStrings = [];

    /** @var array<string, string> sheet name => zip path */
    private array $sheets = [];

    public function __construct(string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $this->zip = new ZipArchive;
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Not a readable XLSX file: {$path}");
        }

        $this->loadSharedStrings();
        $this->loadSheets();
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    public function hasSheet(string $name): bool
    {
        return isset($this->sheets[$name]);
    }

    /**
     * Non-empty cells of a sheet.
     *
     * @return array<int, array<string, string>> row number => [column letter => value]
     */
    public function rows(string $sheet): array
    {
        if (! isset($this->sheets[$sheet])) {
            throw new RuntimeException("Sheet not found: {$sheet}");
        }

        $xml = $this->xml($this->sheets[$sheet]);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $value = $this->cellValue($cell);
                if ($value === null || trim($value) === '') {
                    continue;
                }
                $column = preg_replace('/\d+/', '', (string) $cell['r']);
                $cells[$column] = $value;
            }
            if ($cells !== []) {
                $rows[(int) $row['r']] = $cells;
            }
        }

        return $rows;
    }

    private function cellValue(SimpleXMLElement $cell): ?string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return $this->text($cell->is);
        }
        if (! isset($cell->v)) {
            return null;
        }
        $raw = (string) $cell->v;

        return $type === 's' ? ($this->sharedStrings[(int) $raw] ?? null) : $raw;
    }

    private function loadSharedStrings(): void
    {
        if ($this->zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }

        foreach ($this->xml('xl/sharedStrings.xml')->si as $item) {
            $this->sharedStrings[] = $this->text($item);
        }
    }

    private function loadSheets(): void
    {
        $targets = [];
        foreach ($this->xml('xl/_rels/workbook.xml.rels')->Relationship as $rel) {
            $targets[(string) $rel['Id']] = ltrim(preg_replace('#^/?xl/#', '', (string) $rel['Target']), '/');
        }

        foreach ($this->xml('xl/workbook.xml')->sheets->sheet as $sheet) {
            $id = (string) $sheet->attributes(self::NS_REL)['id'];
            if (isset($targets[$id])) {
                $this->sheets[(string) $sheet['name']] = 'xl/'.$targets[$id];
            }
        }
    }

    /** Concatenates every <t> under a string item (handles rich-text runs). */
    private function text(SimpleXMLElement $node): string
    {
        $node->registerXPathNamespace('m', self::NS_MAIN);

        return implode('', array_map('strval', $node->xpath('.//m:t') ?: []));
    }

    private function xml(string $entry): SimpleXMLElement
    {
        $content = $this->zip->getFromName($entry);
        if ($content === false) {
            throw new RuntimeException("Missing workbook part: {$entry}");
        }

        return new SimpleXMLElement($content);
    }
}
