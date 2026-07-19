<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Exports every user table in the SQLite database to CSV files and packs them into a ZIP.
 */
class DatabaseExportService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{filename: string, contents: string, table_count: int}
     */
    public function exportToZip(): array
    {
        $tables = $this->listTables();
        if ($tables === []) {
            throw new RuntimeException('No tables found to export');
        }

        $csvFiles = [];
        foreach ($tables as $table) {
            $csvFiles[$table . '.csv'] = $this->tableToCsv($table);
        }

        $filename = 'database-export-' . date('Y-m-d-His') . '.zip';
        $contents = $this->buildZip($csvFiles);

        return [
            'filename' => $filename,
            'contents' => $contents,
            'table_count' => count($tables),
        ];
    }

    /**
     * @return list<string>
     */
    public function listTables(): array
    {
        $stmt = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function tableToCsv(string $table): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException('Invalid table name: ' . $table);
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary stream for CSV export');
        }

        $quoted = '"' . str_replace('"', '""', $table) . '"';
        $stmt = $this->db->query('SELECT * FROM ' . $quoted);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            // Still emit column headers for empty tables
            $columnsStmt = $this->db->query('PRAGMA table_info(' . $quoted . ')');
            $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = array_map(static fn (array $col) => $col['name'], $columns);
            if ($headers !== []) {
                fputcsv($handle, $headers);
            }
        } else {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, array_map([$this, 'normalizeCsvValue'], array_values($row)));
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @param array<string, string> $files Map of archive entry name => file contents
     */
    public function buildZip(array $files): string
    {
        if ($files === []) {
            throw new RuntimeException('No files to zip');
        }

        if (class_exists(ZipArchive::class)) {
            return $this->buildZipWithZipArchive($files);
        }

        return $this->buildZipStoreOnly($files);
    }

    /**
     * @param array<string, string> $files
     */
    private function buildZipWithZipArchive(array $files): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'dbexport_');
        if ($tempFile === false) {
            throw new RuntimeException('Unable to create temporary zip file');
        }

        $zipPath = $tempFile . '.zip';
        @unlink($tempFile);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create zip archive');
        }

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $contents = file_get_contents($zipPath);
        @unlink($zipPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read zip archive');
        }

        return $contents;
    }

    /**
     * Minimal ZIP writer (STORE / no compression) for hosts without ext-zip.
     *
     * @param array<string, string> $files
     */
    private function buildZipStoreOnly(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        $count = 0;

        foreach ($files as $name => $contents) {
            $name = str_replace('\\', '/', (string) $name);
            $nameBytes = $name;
            $data = $contents;
            $crc = crc32($data);
            // crc32() returns signed int on 32-bit; pack as unsigned
            $crc = (int) sprintf('%u', $crc);
            $size = strlen($data);
            $nameLen = strlen($nameBytes);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50, // local file header signature
                20,         // version needed
                0,          // general purpose bit flag
                0,          // compression method: STORE
                0,          // last mod file time
                0,          // last mod file date
                $crc,
                $size,
                $size,
                $nameLen,
                0           // extra field length
            );

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, // central file header signature
                20,         // version made by
                20,         // version needed
                0,          // general purpose bit flag
                0,          // compression method
                0,          // last mod file time
                0,          // last mod file date
                $crc,
                $size,
                $size,
                $nameLen,
                0,          // extra field length
                0,          // file comment length
                0,          // disk number start
                0,          // internal file attributes
                0,          // external file attributes
                $offset
            );

            $local .= $localHeader . $nameBytes . $data;
            $central .= $centralHeader . $nameBytes;
            $offset += strlen($localHeader) + $nameLen + $size;
            $count++;
        }

        $end = pack(
            'VvvvvVVv',
            0x06054b50, // end of central directory signature
            0,          // number of this disk
            0,          // disk with start of central directory
            $count,
            $count,
            strlen($central),
            $offset,
            0           // zip file comment length
        );

        return $local . $central . $end;
    }

    private function normalizeCsvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $string = (string) $value;

        // Binary / non-text columns (e.g. logo blobs) — encode so CSV stays valid text
        if (str_contains($string, "\0") || !mb_check_encoding($string, 'UTF-8')) {
            return 'base64:' . base64_encode($string);
        }

        return $string;
    }
}
