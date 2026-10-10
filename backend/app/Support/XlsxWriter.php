<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal XLSX writer (the counterpart of XlsxReader): several sheets of text and numbers, a bold
 * header row, right-to-left sheets and sized columns — enough for exporting tables without a
 * spreadsheet dependency. Text is stored inline, so it is never interpreted as a formula.
 */
final class XlsxWriter
{
    /** @var list<array{name: string, rows: list<list<mixed>>, widths: list<int>, header: bool}> */
    private array $sheets = [];

    /**
     * @param  list<list<mixed>>  $rows  string | int | float | null cells
     * @param  list<int>  $widths  column widths in characters (optional)
     * @param  bool  $header  bold, frozen first row
     */
    public function addSheet(string $name, array $rows, array $widths = [], bool $header = true): self
    {
        // Excel limits: 31 characters, none of \ / ? * [ ] :
        $clean = mb_substr(trim(preg_replace('#[\\\\/?*\[\]:]#u', ' ', $name)), 0, 31);
        $this->sheets[] = ['name' => $clean === '' ? 'Sheet'.(count($this->sheets) + 1) : $clean, 'rows' => $rows, 'widths' => $widths, 'header' => $header];

        return $this;
    }

    /** Writes the workbook to $path. */
    public function save(string $path): void
    {
        if ($this->sheets === []) {
            throw new RuntimeException('A workbook needs at least one sheet.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot write {$path}");
        }

        $count = count($this->sheets);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes($count));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels($count));
        $zip->addFromString('xl/styles.xml', $this->styles());
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $this->sheetXml($sheet));
        }

        $zip->close();
    }

    private function contentTypes(int $count): string
    {
        $sheets = '';
        for ($i = 1; $i <= $count; $i++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$sheets.'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRels(int $count): string
    {
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    /** Style 0 = default, style 1 = bold header with a light fill. */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF3EDE3"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs>'
            .'</styleSheet>';
    }

    /** @param array{name: string, rows: list<list<mixed>>, widths: list<int>, header: bool} $sheet */
    private function sheetXml(array $sheet): string
    {
        $view = '<sheetViews><sheetView rightToLeft="1" workbookViewId="0">'
            .($sheet['header'] ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '')
            .'</sheetView></sheetViews>';

        $cols = '';
        if ($sheet['widths'] !== []) {
            $cols = '<cols>';
            foreach ($sheet['widths'] as $i => $width) {
                $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        $data = '';
        foreach ($sheet['rows'] as $r => $row) {
            $data .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $value) {
                $ref = XlsxReader::columnLetter($c + 1).($r + 1);
                $style = $sheet['header'] && $r === 0 ? ' s="1"' : '';
                if ($value === null || $value === '') {
                    continue;
                }
                if (is_int($value) || is_float($value)) {
                    $data .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
                } else {
                    $data .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
                }
            }
            $data .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$view.$cols.'<sheetData>'.$data.'</sheetData></worksheet>';
    }

    /** XML-safe text: escapes markup and drops characters XML 1.0 forbids. */
    private function escape(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
