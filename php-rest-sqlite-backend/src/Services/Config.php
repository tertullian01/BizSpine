<?php

namespace App\Services;

use Dotenv\Dotenv;

class Config
{
    private static ?Config $instance = null;
    private array $config;

    private function __construct()
    {
        $this->load();
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load(): void
    {
        // Load .env file from the project root
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Load the base configuration file
        $configPath = __DIR__ . '/../../protected/config/config.php';
        if (!file_exists($configPath)) {
            throw new \Exception("Configuration file not found at: {$configPath}");
        }
        $config = require $configPath;

        // Override with environment-specific values if they exist
        $config['database']['database_path'] = $_ENV['DB_DATABASE'] ?? $config['database']['database_path'];
        $config['jwt']['secret'] = $_ENV['JWT_SECRET'] ?? $config['jwt']['secret'];

        $this->config = $config;
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $array = &$this->config;
        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($array[$k]) || !is_array($array[$k])) {
                $array[$k] = [];
            }
            $array = &$array[$k];
        }
        $array[array_shift($keys)] = $value;
    }

    public function getAll(): array
    {
        return $this->config;
    }
}
