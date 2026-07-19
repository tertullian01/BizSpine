<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DatabaseExportService;
use Tests\DatabaseTestCase;

class DatabaseExportServiceTest extends DatabaseTestCase
{
    public function testListTablesExcludesSqliteInternal(): void
    {
        $service = new DatabaseExportService(self::$db);
        $tables = $service->listTables();

        $this->assertNotEmpty($tables);
        foreach ($tables as $table) {
            $this->assertStringStartsNotWith('sqlite_', $table);
        }
        $this->assertContains('users', $tables);
    }

    public function testTableToCsvIncludesHeaderAndRows(): void
    {
        self::$db->exec("INSERT INTO users (email, password_hash, role) VALUES ('export@example.com', 'hash', 'customer')");
        $service = new DatabaseExportService(self::$db);
        $csv = $service->tableToCsv('users');

        $this->assertStringContainsString('email', $csv);
        $this->assertStringContainsString('export@example.com', $csv);
    }

    public function testEmptyTableStillEmitsHeaders(): void
    {
        $service = new DatabaseExportService(self::$db);
        $csv = $service->tableToCsv('products');

        $this->assertStringContainsString('name', $csv);
        $this->assertStringContainsString('cost', $csv);
    }

    public function testExportToZipProducesValidArchive(): void
    {
        self::$db->exec("INSERT INTO products (name, cost) VALUES ('Export Product', 9.99)");
        $service = new DatabaseExportService(self::$db);
        $export = $service->exportToZip();

        $this->assertArrayHasKey('filename', $export);
        $this->assertArrayHasKey('contents', $export);
        $this->assertArrayHasKey('table_count', $export);
        $this->assertStringEndsWith('.zip', $export['filename']);
        $this->assertGreaterThan(0, $export['table_count']);
        $this->assertNotSame('', $export['contents']);
        // ZIP local file header signature
        $this->assertSame("PK\x03\x04", substr($export['contents'], 0, 4));
    }

    public function testBuildZipContainsCsvEntries(): void
    {
        $service = new DatabaseExportService(self::$db);
        $zip = $service->buildZip([
            'users.csv' => "id,email\n1,a@b.com\n",
            'products.csv' => "id,name\n1,Soap\n",
        ]);

        $this->assertSame("PK\x03\x04", substr($zip, 0, 4));
        $this->assertStringContainsString('users.csv', $zip);
        $this->assertStringContainsString('products.csv', $zip);
        $this->assertStringContainsString('a@b.com', $zip);
    }
}
