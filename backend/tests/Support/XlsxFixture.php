<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Builds a minimal .xlsx so importer tests do not depend on the real workbooks.
 *
 * Cell values: string → inline string, int|float → number, ['f' => 'SUM(G2,I2)', 'v' => 5] → formula
 * with its cached result. A sheet may list merged ranges under the '_merges' key.
 */
final class XlsxFixture
{
    /**
     * @param  array<string, array<int|string, mixed>>  $sheets  name => [row number => [column => value], '_merges' => [...]]
     */
    public static function create(array $sheets, ?string $path = null): string
    {
        $path ??= tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $sheetEntries = '';
        $rels = '';
        $i = 1;
        foreach ($sheets as $name => $rows) {
            $merges = $rows['_merges'] ?? [];
            unset($rows['_merges']);
            $sheetEntries .= '<sheet name="'.htmlspecialchars($name).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';

            $data = '';
            foreach ($rows as $number => $cells) {
                $data .= '<row r="'.$number.'">';
                foreach ($cells as $column => $value) {
                    $ref = $column.$number;
                    if (is_array($value)) {
                        $cached = $value['v'] ?? null;
                        $data .= '<c r="'.$ref.'"><f>'.htmlspecialchars($value['f']).'</f>'.($cached === null ? '' : '<v>'.$cached.'</v>').'</c>';
                    } elseif (is_int($value) || is_float($value)) {
                        $data .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                    } else {
                        $data .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value).'</t></is></c>';
                    }
                }
                $data .= '</row>';
            }
            $mergeXml = $merges ? '<mergeCells count="'.count($merges).'">'.implode('', array_map(fn ($m) => '<mergeCell ref="'.$m.'"/>', $merges)).'</mergeCells>' : '';
            $zip->addFromString("xl/worksheets/sheet{$i}.xml",
                '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$data.'</sheetData>'.$mergeXml.'</worksheet>');
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
