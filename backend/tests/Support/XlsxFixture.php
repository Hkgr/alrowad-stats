<?php

namespace Tests\Support;

use ZipArchive;

/** Builds a minimal .xlsx (inline strings) so importer tests do not depend on the real workbook. */
final class XlsxFixture
{
    /**
     * @param  array<string, array<int, array<string, string>>>  $sheets  name => row number => [column => value]
     */
    public static function create(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $sheetEntries = '';
        $rels = '';
        $i = 1;
        foreach ($sheets as $name => $rows) {
            $sheetEntries .= '<sheet name="'.htmlspecialchars($name).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';

            $data = '';
            foreach ($rows as $number => $cells) {
                $data .= '<row r="'.$number.'">';
                foreach ($cells as $column => $value) {
                    $data .= '<c r="'.$column.$number.'" t="inlineStr"><is><t>'.htmlspecialchars($value).'</t></is></c>';
                }
                $data .= '</row>';
            }
            $zip->addFromString("xl/worksheets/sheet{$i}.xml",
                '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$data.'</sheetData></worksheet>');
            $i++;
        }

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetEntries.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->close();

        return $path;
    }
}
