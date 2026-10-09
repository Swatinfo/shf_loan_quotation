<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Minimal, dependency-free .xlsx reader (ZipArchive + SimpleXML) — the read
 * counterpart to XlsxExportService. Reads the first worksheet into header-keyed
 * rows. Handles shared strings, inline strings, and numeric cells; Excel date
 * serials are converted on demand via excelDate() (the caller knows which
 * columns are dates).
 */
class XlsxImportService
{
    /**
     * Read the first worksheet as a list of associative rows keyed by the
     * header (row 1). Blank trailing header columns are ignored.
     *
     * @return array<int, array<string, string>>
     */
    public function readAssoc(string $path): array
    {
        $grid = $this->readGrid($path);
        if (empty($grid)) {
            return [];
        }

        $header = array_map(fn ($h) => trim((string) $h), array_shift($grid));
        $rows = [];
        foreach ($grid as $cells) {
            // Skip fully-empty rows.
            if (! array_filter($cells, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $name) {
                if ($name === '') {
                    continue;
                }
                $row[$name] = $cells[$i] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Read the first worksheet as a 0-indexed grid [rowIndex][colIndex] => value.
     *
     * @return array<int, array<int, ?string>>
     */
    public function readGrid(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open the Excel file.');
        }

        try {
            $shared = $this->readSharedStrings($zip);
            $sheetXml = $this->firstSheetXml($zip);
        } finally {
            $zip->close();
        }

        return $this->parseSheet($sheetXml, $shared);
    }

    /** Convert an Excel date serial to a Carbon date (1900 date system). */
    public static function excelDate($serial): ?CarbonImmutable
    {
        if ($serial === null || $serial === '' || ! is_numeric($serial)) {
            return null;
        }

        // Excel's day 0 is 1899-12-30 (accounts for the 1900 leap-year bug).
        return CarbonImmutable::create(1899, 12, 30)->addDays((int) $serial);
    }

    /** @return array<int, string> */
    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $sst = @simplexml_load_string($xml);
        if (! $sst) {
            return [];
        }

        $strings = [];
        foreach ($sst->si as $si) {
            // Plain <t>, or rich text runs <r><t>…</t></r>.
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
            } else {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
                $strings[] = $text;
            }
        }

        return $strings;
    }

    private function firstSheetXml(\ZipArchive $zip): string
    {
        // sheet1.xml is the usual first sheet; fall back to the lowest-numbered.
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($xml !== false) {
            return $xml;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return (string) $zip->getFromIndex($i);
            }
        }
        throw new \RuntimeException('The Excel file has no worksheet.');
    }

    /**
     * @param  array<int, string>  $shared
     * @return array<int, array<int, ?string>>
     */
    private function parseSheet(string $xml, array $shared): array
    {
        $sheet = @simplexml_load_string($xml);
        if (! $sheet || ! isset($sheet->sheetData)) {
            return [];
        }

        $grid = [];
        $r = 0;
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            $maxCol = -1;
            foreach ($row->c as $c) {
                $ref = (string) ($c['r'] ?? '');
                $col = $this->colIndex($ref);
                $type = (string) ($c['t'] ?? '');
                $value = null;

                if ($type === 's') {
                    $idx = (int) $c->v;
                    $value = $shared[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($c->is->t) ? (string) $c->is->t : '';
                } elseif ($type === 'str') {
                    $value = (string) $c->v;
                } else {
                    $value = isset($c->v) ? (string) $c->v : null;
                }

                $cells[$col] = $value;
                $maxCol = max($maxCol, $col);
            }

            // Normalize to a contiguous 0..maxCol array.
            $normalized = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $normalized[$i] = $cells[$i] ?? null;
            }
            $grid[$r++] = $normalized;
        }

        return $grid;
    }

    /** "C5" → 2 (0-indexed column). */
    private function colIndex(string $ref): int
    {
        $letters = preg_replace('/[0-9]+/', '', $ref);
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }
}
