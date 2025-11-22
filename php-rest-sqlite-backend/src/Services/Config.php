<?php

namespace App\Services;

use Dotenv\Dotenv;

class Config
{
    private static ?array $config = null;

    private static function load(): void
    {
        if (self::$config === null) {
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

            self::$config = $config;
        }
    }

    public static function get(string $key, $default = null)
    {
        self::load();
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        self::load();
        $keys = explode('.', $key);
        $array = &self::$config;
        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($array[$k]) || !is_array($array[$k])) {
                $array[$k] = [];
            }
            $array = &$array[$k];
        }
        $array[array_shift($keys)] = $value;
    }
}
