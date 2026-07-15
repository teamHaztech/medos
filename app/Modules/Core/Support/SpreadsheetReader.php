<?php

namespace App\Modules\Core\Support;

/**
 * Reads CSV and XLSX spreadsheets into associative rows, with NO external
 * dependency (uses PHP's built-in fgetcsv, ZipArchive and SimpleXML). XLSX is
 * an OOXML zip: we read xl/sharedStrings.xml for text and the first worksheet.
 *
 * Usage:
 *   $data = SpreadsheetReader::read($path, $originalName);
 *   // ['headers' => ['name','price',...], 'rows' => [['name'=>'X','price'=>'10'], ...]]
 *
 * Headers are normalised to snake_case (lowercased, non-alphanumerics -> "_")
 * so "Selling Price", "selling_price" and "SELLING PRICE" all map to the same key.
 */
class SpreadsheetReader
{
    /**
     * @return array{headers: array<int,string>, rows: array<int, array<string,string>>}
     */
    public static function read(string $path, ?string $originalName = null): array
    {
        $ext = strtolower(pathinfo($originalName ?? $path, PATHINFO_EXTENSION));
        $matrix = $ext === 'xlsx' ? self::readXlsx($path) : self::readCsv($path);

        $headerRow = null;
        $headers = [];
        $rows = [];

        foreach ($matrix as $cells) {
            $nonEmpty = array_filter($cells, fn ($c) => trim((string) $c) !== '');

            if ($headerRow === null) {
                if (count($nonEmpty) === 0) {
                    continue; // skip blank leading rows
                }
                $headerRow = array_map(fn ($c) => self::normalizeHeader((string) $c), $cells);
                $headers = array_values(array_filter($headerRow, fn ($h) => $h !== ''));
                continue;
            }

            if (count($nonEmpty) === 0) {
                continue; // skip blank rows in the body
            }

            $row = [];
            foreach ($headerRow as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    public static function normalizeHeader(string $h): string
    {
        $h = strtolower(trim($h));
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);

        return trim($h, '_');
    }

    /** @return array<int, array<int,string>> */
    private static function readCsv(string $path): array
    {
        $out = [];
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return $out;
        }

        // Strip a UTF-8 BOM if present.
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // Explicit enclosure + escape ("") — PHP 8.4 deprecates relying on defaults.
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $out[] = array_map(fn ($c) => (string) $c, $row);
        }
        fclose($fh);

        return $out;
    }

    /** @return array<int, array<int,string>> */
    private static function readXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        // Shared strings table (most text cells reference it by index).
        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false && ($xml = @simplexml_load_string($ssXml)) !== false && $xml) {
            foreach ($xml->si as $si) {
                $shared[] = self::richText($si);
            }
        }

        // First worksheet — prefer sheet1.xml, else the lowest-numbered sheet.
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheetXml = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close();

        if (! $sheetXml || ($xml = @simplexml_load_string($sheetXml)) === false || ! $xml) {
            return [];
        }

        $out = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];                       // e.g. "B7"
                $col = self::colIndex(preg_replace('/\d+/', '', $ref));
                $type = (string) $c['t'];

                if ($type === 's') {
                    $val = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = self::richText($c->is);
                } else {
                    $val = (string) $c->v;
                }
                $cells[$col] = $val;
            }

            // Flatten sparse cells into a 0-indexed row, filling gaps.
            $max = $cells === [] ? -1 : max(array_keys($cells));
            $flat = [];
            for ($i = 0; $i <= $max; $i++) {
                $flat[] = $cells[$i] ?? '';
            }
            $out[] = $flat;
        }

        return $out;
    }

    /** Concatenate all <t> text inside an <si>/<is> node (handles rich-text runs). */
    private static function richText(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        if (isset($node->r)) {
            foreach ($node->r as $r) {
                $text .= (string) $r->t;
            }
        }

        return $text;
    }

    /** Excel column letters ("A", "AB") to a 0-based index. */
    private static function colIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }

        return $n - 1;
    }
}
