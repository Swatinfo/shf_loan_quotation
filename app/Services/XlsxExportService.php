<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Minimal .xlsx writer built on ZipArchive — no external spreadsheet
 * package. Emits a single-sheet workbook with inline strings (no
 * sharedStrings part), real numeric cells for amounts/counts and real
 * date-serial cells (dd/mm/yyyy display) so the exported columns sort
 * and sum natively in Excel.
 */
class XlsxExportService
{
    public const TYPE_STRING = 'string';

    /** Integer-ish numbers displayed with thousands grouping (#,##0). */
    public const TYPE_NUMBER = 'number';

    /** Numbers rendered as-is (keeps decimals, e.g. averages). */
    public const TYPE_DECIMAL = 'decimal';

    /** Date cells — accepts Y-m-d / Y-m-d H:i:s strings, shown dd/mm/yyyy. */
    public const TYPE_DATE = 'date';

    /** Long multi-line text — wrapped, top-aligned, wide column. */
    public const TYPE_WRAP = 'wrap';

    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** Style indexes fixed by stylesXml() — keep the two in sync. */
    private const S_DEFAULT = 0;

    private const S_BOLD = 1;

    private const S_NUMBER = 2;

    private const S_DATE = 3;

    private const S_WRAP = 4;

    private const S_BOLD_NUMBER = 5;

    /**
     * Build the workbook in a temp file and return it as a download.
     * The response deletes the temp file after sending.
     *
     * @param  array<int, string>  $headers  Row-1 labels (bold)
     * @param  iterable<int, array<int, mixed>>  $rows  Cell values; null/'' => empty cell
     * @param  array<int, string>  $columnTypes  Column index => TYPE_* (default string)
     * @param  array<int, array<int, mixed>>  $footerRows  Bold totals rows appended last
     */
    public function download(
        string $filename,
        array $headers,
        iterable $rows,
        array $columnTypes = [],
        array $footerRows = [],
        string $sheetName = 'Report',
    ): BinaryFileResponse {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($headers, $rows, $columnTypes, $footerRows));
        $zip->close();

        return response()->download($path, $filename, ['Content-Type' => self::XLSX_MIME])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $columnTypes
     * @param  array<int, array<int, mixed>>  $footerRows
     */
    private function sheetXml(array $headers, iterable $rows, array $columnTypes, array $footerRows): string
    {
        $cols = '';
        foreach (array_keys($headers) as $i) {
            $type = $columnTypes[$i] ?? self::TYPE_STRING;
            $width = max($this->columnWidth($type), mb_strlen($headers[$i]) + 4);
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols>'.$cols.'</cols><sheetData>';

        $rowNum = 1;
        $xml .= $this->rowXml($rowNum, $headers, [], bold: true);

        foreach ($rows as $row) {
            $rowNum++;
            $xml .= $this->rowXml($rowNum, $row, $columnTypes);
        }
        foreach ($footerRows as $row) {
            $rowNum++;
            $xml .= $this->rowXml($rowNum, $row, $columnTypes, bold: true);
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<int, string>  $columnTypes
     */
    private function rowXml(int $rowNum, array $cells, array $columnTypes, bool $bold = false): string
    {
        $xml = '<row r="'.$rowNum.'">';
        foreach (array_values($cells) as $i => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $ref = $this->colRef($i).$rowNum;
            $xml .= $this->cellXml($ref, $value, $columnTypes[$i] ?? self::TYPE_STRING, $bold);
        }

        return $xml.'</row>';
    }

    private function cellXml(string $ref, mixed $value, string $type, bool $bold): string
    {
        if (in_array($type, [self::TYPE_NUMBER, self::TYPE_DECIMAL], true) && is_numeric($value)) {
            $style = $type === self::TYPE_NUMBER ? ($bold ? self::S_BOLD_NUMBER : self::S_NUMBER) : self::S_DEFAULT;

            return '<c r="'.$ref.'" s="'.$style.'"><v>'.(0 + $value).'</v></c>';
        }

        if ($type === self::TYPE_DATE && ($serial = $this->dateSerial($value)) !== null) {
            return '<c r="'.$ref.'" s="'.self::S_DATE.'"><v>'.$serial.'</v></c>';
        }

        $style = $bold ? self::S_BOLD : ($type === self::TYPE_WRAP ? self::S_WRAP : self::S_DEFAULT);

        return '<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'
            .$this->esc((string) $value).'</t></is></c>';
    }

    /**
     * Excel 1900-system serial for a Y-m-d(-prefixed) date. Parsed at UTC
     * midnight so the local timezone can never shift the day.
     */
    private function dateSerial(mixed $value): ?int
    {
        if (! preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $value, $m)) {
            return null;
        }
        $ts = strtotime($m[1].' 00:00:00 UTC');

        return $ts === false ? null : intdiv($ts, 86400) + 25569;
    }

    /**
     * XML-escape and strip characters that are illegal in XML 1.0 — the
     * usual cause of Excel "repair" prompts from user-entered text.
     */
    private function esc(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Spreadsheet column reference: 0 => A, 25 => Z, 26 => AA … */
    private function colRef(int $index): string
    {
        $ref = '';
        $n = $index + 1;
        while ($n > 0) {
            $n--;
            $ref = chr(65 + ($n % 26)).$ref;
            $n = intdiv($n, 26);
        }

        return $ref;
    }

    private function columnWidth(string $type): int
    {
        return match ($type) {
            self::TYPE_NUMBER, self::TYPE_DECIMAL, self::TYPE_DATE => 14,
            self::TYPE_WRAP => 50,
            default => 20,
        };
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        // Sheet names: max 31 chars, none of \ / : * ? [ ]
        $name = mb_substr(preg_replace('/[\\\\\/:*?\[\]]/', ' ', $sheetName) ?: 'Report', 0, 31);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->esc($name).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            .'<xf numFmtId="3" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
