<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DatabasePool;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class DatabasePoolTest extends TestCase
{
    private string $testDbPath;

    protected function setUp(): void
    {
        $this->testDbPath = sys_get_temp_dir() . '/test_db_' . uniqid() . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
    }

    public function testPoolCreation(): void
    {
        $pool = new DatabasePool('sqlite:' . $this->testDbPath, 3);
        $this->assertInstanceOf(DatabasePool::class, $pool);
    }

    public function testGetConnectionReturnsPDO(): void
    {
        $pool = new DatabasePool('sqlite:' . $this->testDbPath, 2);

        $connection = $pool->getConnection();
        $this->assertInstanceOf(PDO::class, $connection);

        // Test that we can execute queries
        $result = $connection->query('SELECT 1 as test');
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, $row['test']);
    }

    public function testConnectionPooling(): void
    {
        $pool = new DatabasePool('sqlite:' . $this->testDbPath, 2);

        // Get two connections
        $conn1 = $pool->getConnection();
        $conn2 = $pool->getConnection();

        $this->assertInstanceOf(PDO::class, $conn1);
        $this->assertInstanceOf(PDO::class, $conn2);

        // They should be different objects (different connections)
        $this->assertNotSame($conn1, $conn2);
    }

    public function testConnectionRelease(): void
    {
        $pool = new DatabasePool('sqlite:' . $this->testDbPath, 1);

        $conn1 = $pool->getConnection();
        $this->assertInstanceOf(PDO::class, $conn1);

        // Return connection to pool
        $pool->returnConnection($conn1);

        // Check pool size
        $this->assertEquals(1, $pool->getPoolSize());
    }

    public function testPoolLimits(): void
    {
        $pool = new DatabasePool('sqlite:' . $this->testDbPath, 1);

        $conn1 = $pool->getConnection();
        $this->assertInstanceOf(PDO::class, $conn1);

        // Return connection to pool
        $pool->returnConnection($conn1);

        // Get connection again - should reuse from pool
        $conn2 = $pool->getConnection();
        $this->assertInstanceOf(PDO::class, $conn2);
        $this->assertEquals(0, $pool->getPoolSize()); // Should be taken from pool
    }
}