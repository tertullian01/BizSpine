<?php
declare(strict_types=1);

// Initialize SQLite DB and schema for the project.
// Run from project root:
// php php-rest-sqlite-backend/protected/scripts/init_db.php

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Config not found at: $configPath\n");
    exit(1);
}

$config = require $configPath;
$dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
if (!$dbPath) {
    fwrite(STDERR, "db_path not set in config (expected 'db_path' or 'database.database_path')\n");
    exit(1);
}

$dir = dirname($dbPath);
if (!is_dir($dir)) {
    if (!mkdir($dir, 0700, true)) {
        fwrite(STDERR, "Failed to create directory: $dir\n");
        exit(1);
    }
    @chmod($dir, 0700);
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT UNIQUE,
  password_hash TEXT,
  display_name TEXT,
  is_email_verified INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME
);
SQL
    );

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_providers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  provider TEXT NOT NULL,
  provider_user_id TEXT NOT NULL,
  access_token TEXT,
  refresh_token TEXT,
  token_expires_at DATETIME,
  UNIQUE(provider, provider_user_id),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
    );

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL,
  revoked INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
    );

    echo "Database initialized at: $dbPath\n";

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables:\n - " . implode("\n - ", $tables) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error initializing DB: " . $e->getMessage() . "\n");
    exit(1);
}