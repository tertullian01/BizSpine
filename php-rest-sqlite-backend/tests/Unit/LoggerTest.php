<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class LoggerTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger('test-channel', 'php://memory');
    }

    public function testLoggerCreation(): void
    {
        $this->assertInstanceOf(Logger::class, $this->logger);
    }

    public function testInfoLogging(): void
    {
        $this->logger->info('Test info message', ['key' => 'value']);
        $this->assertTrue(true); // If no exception, test passes
    }

    public function testErrorLogging(): void
    {
        $this->logger->error('Test error message', ['error' => 'details']);
        $this->assertTrue(true); // If no exception, test passes
    }

    public function testWarningLogging(): void
    {
        $this->logger->warning('Test warning message');
        $this->assertTrue(true); // If no exception, test passes
    }

    public function testDebugLogging(): void
    {
        $this->logger->debug('Test debug message', ['debug' => 'info']);
        $this->assertTrue(true); // If no exception, test passes
    }

    public function testContextualLogging(): void
    {
        $this->logger->info('User action', [
            'user_id' => 123,
            'action' => 'login',
            'ip' => '192.168.1.1'
        ]);
        $this->assertTrue(true);
    }
}