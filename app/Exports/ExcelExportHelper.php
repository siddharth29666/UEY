<?php

namespace App\Exports;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ExcelExportHelper
{
    /**
     * Export tabular data as a real binary OpenXML (.xlsx) Excel workbook.
     *
     * @param string $filename Base filename without extension
     * @param array $headers Array of header string column names
     * @param array $rows Array of row data arrays
     * @return StreamedResponse
     */
    public static function streamXlsx(string $filename, array $headers, array $rows): StreamedResponse
    {
        $fullFilename = $filename . '_' . date('Y-m-d_His') . '.xlsx';

        return response()->stream(
            function () use ($headers, $rows) {
                $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_');

                $zip = new ZipArchive();
                if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

                    // 1. Build Shared Strings XML & Index
                    $sharedStrings = [];
                    $stringIndexMap = [];
                    $getStringIndex = function ($value) use (&$sharedStrings, &$stringIndexMap) {
                        $str = (string) $value;
                        if (!isset($stringIndexMap[$str])) {
                            $idx = count($sharedStrings);
                            $sharedStrings[] = $str;
                            $stringIndexMap[$str] = $idx;
                        }
                        return $stringIndexMap[$str];
                    };

                    // Index header strings
                    foreach ($headers as $header) {
                        $getStringIndex($header);
                    }

                    // Index row strings
                    foreach ($rows as $row) {
                        foreach ($row as $cell) {
                            if (!is_numeric($cell) || is_null($cell)) {
                                $getStringIndex($cell ?? '');
                            }
                        }
                    }

                    // Build xl/sharedStrings.xml
                    $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
                    $sharedStringsXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
                    foreach ($sharedStrings as $str) {
                        $escaped = htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
                        $sharedStringsXml .= '<si><t>' . $escaped . '</t></si>';
                    }
                    $sharedStringsXml .= '</sst>';
                    $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);

                    // 2. Build xl/worksheets/sheet1.xml
                    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
                    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
                    $sheetXml .= '<sheetData>';

                    // Add Header Row (Row 1) with Bold Style (s="1")
                    $sheetXml .= '<row r="1">';
                    foreach ($headers as $colIdx => $header) {
                        $colLetter = self::columnLetter($colIdx + 1);
                        $cellRef = $colLetter . '1';
                        $sIdx = $getStringIndex($header);
                        $sheetXml .= '<c r="' . $cellRef . '" t="s" s="1"><v>' . $sIdx . '</v></c>';
                    }
                    $sheetXml .= '</row>';

                    // Add Data Rows (Row 2+)
                    $rowNum = 2;
                    foreach ($rows as $row) {
                        $sheetXml .= '<row r="' . $rowNum . '">';
                        $colIdx = 0;
                        foreach ($row as $cell) {
                            $colLetter = self::columnLetter($colIdx + 1);
                            $cellRef = $colLetter . $rowNum;

                            if (is_numeric($cell) && !preg_match('/^0\d+/', (string)$cell)) {
                                // Numeric cell
                                $sheetXml .= '<c r="' . $cellRef . '" t="n"><v>' . $cell . '</v></c>';
                            } else {
                                // Shared String cell
                                $sIdx = $getStringIndex($cell ?? '');
                                $sheetXml .= '<c r="' . $cellRef . '" t="s"><v>' . $sIdx . '</v></c>';
                            }
                            $colIdx++;
                        }
                        $sheetXml .= '</row>';
                        $rowNum++;
                    }

                    $sheetXml .= '</sheetData>';
                    $sheetXml .= '</worksheet>';
                    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

                    // 3. Add [Content_Types].xml
                    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
                    $contentTypesXml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
                    $contentTypesXml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
                    $contentTypesXml .= '<Default Extension="xml" ContentType="application/xml"/>';
                    $contentTypesXml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
                    $contentTypesXml .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                    $contentTypesXml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
                    $contentTypesXml .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
                    $contentTypesXml .= '</Types>';
                    $zip->addFromString('[Content_Types].xml', $contentTypesXml);

                    // 4. Add _rels/.rels
                    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
                    $relsXml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
                    $relsXml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
                    $relsXml .= '</Relationships>';
                    $zip->addFromString('_rels/.rels', $relsXml);

                    // 5. Add xl/_rels/workbook.xml.rels
                    $wbRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
                    $wbRelsXml .= '<Relationships xmlns="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
                    $wbRelsXml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
                    $wbRelsXml .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
                    $wbRelsXml .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
                    $wbRelsXml .= '</Relationships>';
                    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRelsXml);

                    // 6. Add xl/workbook.xml
                    $wbXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
                    $wbXml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
                    $wbXml .= '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>';
                    $wbXml .= '</workbook>';
                    $zip->addFromString('xl/workbook.xml', $wbXml);

                    // 7. Add xl/styles.xml
                    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
                    $stylesXml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
                    $stylesXml .= '<fonts count="2">';
                    $stylesXml .= '<font><sz val="11"/><name val="Calibri"/></font>';
                    $stylesXml .= '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font>';
                    $stylesXml .= '</fonts>';
                    $stylesXml .= '<fills count="2">';
                    $stylesXml .= '<fill><patternFill fillType="none"/></fill>';
                    $stylesXml .= '<fill><patternFill fillType="gray125"/></fill>';
                    $stylesXml .= '</fills>';
                    $stylesXml .= '<borders count="1">';
                    $stylesXml .= '<border><left/><right/><top/><bottom/><diagonal/></border>';
                    $stylesXml .= '</borders>';
                    $stylesXml .= '<cellStyleXfs count="1">';
                    $stylesXml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>';
                    $stylesXml .= '</cellStyleXfs>';
                    $stylesXml .= '<cellXfs count="2">';
                    $stylesXml .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
                    $stylesXml .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>';
                    $stylesXml .= '</cellXfs>';
                    $stylesXml .= '</styleSheet>';
                    $zip->addFromString('xl/styles.xml', $stylesXml);

                    $zip->close();
                }

                readfile($tempPath);
                @unlink($tempPath);
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fullFilename . '"',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'public',
            ]
        );
    }

    /**
     * Convert 1-based column number to Excel column letters (A, B, C... Z, AA, AB...).
     */
    private static function columnLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $mod = ($col - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $col = (int) (($col - $mod) / 26);
        }
        return $letter;
    }
}
