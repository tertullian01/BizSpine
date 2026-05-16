<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Logger;
use App\Services\Metrics;
use PHPUnit\Framework\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class MetricsTest extends TestCase
{
    private Metrics $metrics;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger('test-metrics', 'php://memory');
        $this->metrics = new Metrics($this->logger);
    }

    public function testMetricsCreation(): void
    {
        $this->assertInstanceOf(Metrics::class, $this->metrics);
    }

    public function testRecordRequest(): void
    {
        $this->metrics->recordRequest('GET', '/api/test', 200, 45.2);
        $this->assertTrue(true); // If no exception, test passes
    }

    public function testGetMetrics(): void
    {
        $this->metrics->recordRequest('GET', '/api/test', 200, 45.2);
        $this->metrics->recordRequest('POST', '/api/create', 201, 67.8);

        $metrics = $this->metrics->getMetrics();
        $this->assertIsArray($metrics);
        $this->assertCount(2, $metrics);
        $this->assertEquals('GET', $metrics[0]['method']);
        $this->assertEquals('/api/test', $metrics[0]['path']);
        $this->assertEquals(200, $metrics[0]['status_code']);
        $this->assertEquals(45.2, $metrics[0]['duration']);
    }

    public function testGetRequestCount(): void
    {
        $this->assertEquals(0, $this->metrics->getRequestCount());

        $this->metrics->recordRequest('GET', '/api/test', 200, 45.2);
        $this->assertEquals(1, $this->metrics->getRequestCount());

        $this->metrics->recordRequest('POST', '/api/create', 201, 67.8);
        $this->assertEquals(2, $this->metrics->getRequestCount());
    }

    public function testGetAverageResponseTime(): void
    {
        $this->assertEquals(0.0, $this->metrics->getAverageResponseTime());

        $this->metrics->recordRequest('GET', '/api/test', 200, 100.0);
        $this->assertEquals(100.0, $this->metrics->getAverageResponseTime());

        $this->metrics->recordRequest('POST', '/api/create', 201, 200.0);
        $this->assertEquals(150.0, $this->metrics->getAverageResponseTime());
    }
}