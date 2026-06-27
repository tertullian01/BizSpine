<?php

declare(strict_types=1);

use App\Services\CorsOriginHelper;
use Phinx\Console\PhinxApplication;
use Phinx\Wrapper\TextWrapper;

const BIZSPINE_DEMO_PASSWORD = 'Example123!';

function bizspine_load_config(string $backendRoot): array
{
    $path = rtrim($backendRoot, '/\\') . '/protected/config/config.php';
    if (!is_file($path)) {
        throw new RuntimeException("Config not found: {$path}");
    }

    return require $path;
}

function bizspine_resolve_db_path(array $config, string $backendRoot): string
{
    $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
    if (!$dbPath) {
        throw new RuntimeException('database.database_path is not set in config.');
    }

    if (!str_starts_with($dbPath, '/') && !preg_match('#^[A-Za-z]:\\\\#', $dbPath)) {
        $dbPath = rtrim($backendRoot, '/\\') . '/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dbPath), '.\\');
    }

    return $dbPath;
}

function bizspine_connect(string $dbPath): PDO
{
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    return $pdo;
}

function bizspine_delete_database_file(string $dbPath): void
{
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    if (is_file($dbPath)) {
        unlink($dbPath);
    }
}

function bizspine_run_migrations(string $backendRoot): string
{
    $phinxConfig = rtrim($backendRoot, '/\\') . '/phinx.php';
    if (!class_exists(TextWrapper::class)) {
        throw new RuntimeException('Phinx is not installed. Run composer install in the backend folder.');
    }

    $app = new PhinxApplication();
    $wrapper = new TextWrapper($app, [
        'configuration' => $phinxConfig,
        'parser' => 'php',
    ]);

    $output = trim($wrapper->getMigrate());
    $exitCode = $wrapper->getExitCode();
    if ($exitCode !== 0) {
        throw new RuntimeException(
            $output !== '' ? $output : 'Phinx migrate failed with exit code ' . $exitCode
        );
    }

    return $output;
}

function bizspine_clear_all_data(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF;');
    $tables = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'phinxlog'"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
    $pdo->exec('DELETE FROM sqlite_sequence');
    $pdo->exec('PRAGMA foreign_keys = ON;');
}

function bizspine_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
    $stmt->execute([':name' => $table]);
    return (bool) $stmt->fetchColumn();
}

function bizspine_ensure_optional_columns(PDO $pdo): void
{
    $userColumns = [
        'first_name' => 'TEXT',
        'last_name' => 'TEXT',
        'country' => 'TEXT',
        'street_line_1' => 'TEXT',
        'street_line_2' => 'TEXT',
        'city' => 'TEXT',
        'state' => 'TEXT',
        'postal_code' => 'TEXT',
        'mobile_number' => 'TEXT',
        'whatsapp_number' => 'TEXT',
        'instagram_link' => 'TEXT',
        'facebook_link' => 'TEXT',
        'reset_token' => 'TEXT',
        'reset_expires_at' => 'DATETIME',
    ];

    foreach ($userColumns as $column => $type) {
        bizspine_add_column_if_missing($pdo, 'users', $column, $type);
    }
}

function bizspine_add_column_if_missing(PDO $pdo, string $table, string $column, string $type): void
{
    $info = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($info as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $type));
}

function bizspine_database_has_users(PDO $pdo): bool
{
    if (!bizspine_table_exists($pdo, 'users')) {
        return false;
    }

    return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

function bizspine_create_admin_user(PDO $pdo, string $email, string $password, string $displayName = 'Site Administrator'): void
{
    bizspine_ensure_optional_columns($pdo);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException('A user with that email already exists.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare(
        'INSERT INTO users (email, password_hash, display_name, role, is_email_verified, created_at)
         VALUES (:email, :hash, :display, :role, 1, datetime("now"))'
    )->execute([
        ':email' => $email,
        ':hash' => $hash,
        ':display' => $displayName,
        ':role' => 'admin',
    ]);
}

function bizspine_write_env_file(string $backendRoot, string $jwtSecret): void
{
    $path = rtrim($backendRoot, '/\\') . '/.env';
    $content = "JWT_SECRET={$jwtSecret}\nALLOW_INSECURE_SETUP=false\n";
    file_put_contents($path, $content);
}

function bizspine_write_install_local_config(string $backendRoot, string $siteOrigin, string $storeName): void
{
    $path = rtrim($backendRoot, '/\\') . '/protected/config/install_local.php';
    $export = var_export([
        'app' => [
            'storefront_url' => rtrim($siteOrigin, '/'),
            'password_reset_path' => '/reset.html',
        ],
        'cors' => [
            'allowed_origins' => CorsOriginHelper::expandWwwVariants([rtrim($siteOrigin, '/')]),
        ],
        'environment' => [
            'debug' => false,
        ],
        'settings_updates' => [
            'store_name' => $storeName,
        ],
    ], true);

    file_put_contents(
        $path,
        "<?php\n\n// Written by the BizSpine web installer. Safe to delete after setup.\nreturn {$export};\n"
    );
}

function bizspine_apply_settings_updates(PDO $pdo, array $updates): void
{
    if (!bizspine_table_exists($pdo, 'settings')) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE settings SET value = :v, updated_at = datetime("now") WHERE key = :k');
    foreach ($updates as $key => $value) {
        $stmt->execute([':v' => $value, ':k' => $key]);
    }
}

function bizspine_detect_backend_root_from_web(string $webRoot): string
{
    $home = dirname($webRoot, 2);
    $candidate = $home . DIRECTORY_SEPARATOR . 'bizspine-backend';
    if (is_file($candidate . '/public/index.php')) {
        return $candidate;
    }

    throw new RuntimeException(
        'Could not find bizspine-backend next to public_html. Upload the folder from the ZIP as described in INSTALL.html.'
    );
}

function bizspine_installed_lock_path(string $webRoot): string
{
    return rtrim($webRoot, '/\\') . '/.bizspine-installed';
}

function bizspine_is_installed(string $webRoot, ?PDO $pdo = null): bool
{
    if (is_file(bizspine_installed_lock_path($webRoot))) {
        return true;
    }

    return $pdo !== null && bizspine_database_has_users($pdo);
}

function bizspine_mark_installed(string $webRoot, array $meta = []): void
{
    $payload = array_merge([
        'installed_at' => date('c'),
    ], $meta);

    file_put_contents(bizspine_installed_lock_path($webRoot), json_encode($payload, JSON_PRETTY_PRINT));
}

function bizspine_ensure_writable_paths(string $backendRoot): array
{
    $paths = [
        rtrim($backendRoot, '/\\') . '/protected/db',
        rtrim($backendRoot, '/\\') . '/uploads',
        rtrim($backendRoot, '/\\') . '/public/logs',
    ];

    $warnings = [];
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        if (!is_writable($path)) {
            $warnings[] = $path;
        }
    }

    return $warnings;
}

function bizspine_generate_secret(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
