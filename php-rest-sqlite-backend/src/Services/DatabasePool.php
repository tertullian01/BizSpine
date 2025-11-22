<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class DatabasePool
{
    private array $pool = [];
    private int $maxConnections;
    private string $dsn;
    private ?string $username;
    private ?string $password;
    private array $options;

    public function __construct(string $dsn, int $maxConnections = 10, ?string $username = null, ?string $password = null, array $options = [])
    {
        $this->dsn = $dsn;
        $this->maxConnections = $maxConnections;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
    }

    public function getConnection(): PDO
    {
        if (!empty($this->pool)) {
            return array_pop($this->pool);
        }

        if (count($this->pool) < $this->maxConnections) {
            return new PDO($this->dsn, $this->username, $this->password, $this->options);
        }

        // Wait or throw exception - for simplicity, throw
        throw new \RuntimeException('No available connections in pool');
    }

    public function returnConnection(PDO $pdo): void
    {
        if (count($this->pool) < $this->maxConnections) {
            $this->pool[] = $pdo;
        }
    }

    public function getPoolSize(): int
    {
        return count($this->pool);
    }
}