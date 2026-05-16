<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Logger;

class Metrics
{
    private Logger $logger;
    private array $metrics = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function recordRequest(string $method, string $path, int $statusCode, float $duration): void
    {
        $metric = [
            'method' => $method,
            'path' => $path,
            'status_code' => $statusCode,
            'duration' => $duration,
            'timestamp' => time(),
        ];

        $this->metrics[] = $metric;

        // Log the metric
        $this->logger->info('Request metric recorded', $metric);

        // In a real system, you might send to monitoring service like Prometheus, DataDog, etc.
        // For now, just log and store in memory
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getRequestCount(): int
    {
        return count($this->metrics);
    }

    public function getAverageResponseTime(): float
    {
        if (empty($this->metrics)) {
            return 0.0;
        }

        $total = array_sum(array_column($this->metrics, 'duration'));
        return $total / count($this->metrics);
    }
}