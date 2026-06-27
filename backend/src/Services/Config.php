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
        $backendRoot = realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);

        // Load .env from backend root (same level as src/, api/, vendor/).
        if (is_file($backendRoot . '/.env')) {
            $dotenv = Dotenv::createImmutable($backendRoot);
            $dotenv->safeLoad();
        }

        // Load the base configuration file
        $configPath = __DIR__ . '/../../protected/config/config.php';
        if (!file_exists($configPath)) {
            throw new \Exception("Configuration file not found at: {$configPath}");
        }
        $config = require $configPath;

        // Override with environment-specific values if they exist
        $config['database']['database_path'] = $_ENV['DB_DATABASE'] ?? $config['database']['database_path'];
        $config['jwt']['secret'] = $_ENV['JWT_SECRET'] ?? $config['jwt']['secret'];
        $config['app']['storefront_url'] = self::resolveStorefrontUrl($config);
        $config['app']['password_reset_url'] = self::resolvePasswordResetUrl($config);

        $origins = $config['cors']['allowed_origins'] ?? [];
        $corsOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? getenv('CORS_ALLOWED_ORIGINS');
        if ($corsOrigins !== false && $corsOrigins !== null && $corsOrigins !== ''
            && !self::isPlaceholderValue((string) $corsOrigins)) {
            $fromEnv = array_values(array_filter(array_map('trim', explode(',', (string) $corsOrigins))));
            $origins = array_merge($origins, $fromEnv);
        }
        $config['cors']['allowed_origins'] = CorsOriginHelper::finalize(
            $origins,
            !empty($config['environment']['debug'])
        );

        $allowSetup = $_ENV['ALLOW_INSECURE_SETUP'] ?? getenv('ALLOW_INSECURE_SETUP');
        if ($allowSetup === false || $allowSetup === null || $allowSetup === '') {
            // Keep value from config.php / install_local.php when .env omits the flag.
            if (!array_key_exists('security', $config) || !array_key_exists('allow_insecure_setup', $config['security'])) {
                $config['security']['allow_insecure_setup'] = false;
            }
        } else {
            $config['security']['allow_insecure_setup'] = filter_var($allowSetup, FILTER_VALIDATE_BOOLEAN)
                || $allowSetup === '1'
                || $allowSetup === 1;
        }

        $this->config = $config;
    }

    public static function isPlaceholderValue(string $value): bool
    {
        return $value === ''
            || str_contains($value, 'INSERT_')
            || str_contains($value, 'COMMA_SEPARATED');
    }

    private static function resolveStorefrontUrl(array $config): string
    {
        $fromEnv = trim((string) ($_ENV['STOREFRONT_URL'] ?? ''));
        if ($fromEnv !== '' && !self::isPlaceholderValue($fromEnv)) {
            return rtrim($fromEnv, '/');
        }

        $fromConfig = trim((string) ($config['app']['storefront_url'] ?? ''));
        if ($fromConfig !== '' && !self::isPlaceholderValue($fromConfig)) {
            return rtrim($fromConfig, '/');
        }

        return '';
    }

    private static function resolvePasswordResetUrl(array $config): string
    {
        $fromEnv = trim((string) ($_ENV['PASSWORD_RESET_URL'] ?? ''));
        if ($fromEnv !== '' && !self::isPlaceholderValue($fromEnv)) {
            return rtrim($fromEnv, '/');
        }

        $storefrontUrl = self::resolveStorefrontUrl($config);
        if ($storefrontUrl === '') {
            return '';
        }

        $path = trim((string) ($_ENV['PASSWORD_RESET_PATH'] ?? ($config['app']['password_reset_path'] ?? '/reset.html')));
        if ($path === '' || self::isPlaceholderValue($path)) {
            $path = '/reset.html';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $storefrontUrl . $path;
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
