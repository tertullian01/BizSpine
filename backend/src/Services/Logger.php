<?php

declare(strict_types=1);

namespace App\Services;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

class Logger
{
    private MonologLogger $logger;

    public function __construct(string $name = 'api', string $logFile = 'logs/api.log', int $level = MonologLogger::INFO)
    {
        $this->logger = new MonologLogger($name);
        $this->logger->pushHandler(new StreamHandler($logFile, $level));
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function getLogger(): MonologLogger
    {
        return $this->logger;
    }
}