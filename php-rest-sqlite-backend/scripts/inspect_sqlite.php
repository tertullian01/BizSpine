<?php
/**
 * Print SQLite tables and columns (PRAGMA table_info).
 *
 * Usage (from php-rest-sqlite-backend):
 *   php scripts/inspect_sqlite.php
 *   php scripts/inspect_sqlite.php path/to/database.db
 */
declare(strict_types=1);

$defaultDb = __DIR__ . '/../protected/database/testing.db';
$dbPath = $argv[1] ?? $defaultDb;

if (!is_readable($dbPath)) {
    fwrite(STDERR, "Database not readable: {$dbPath}\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "File: {$dbPath}\n\n";

    $stmt = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
    );
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($tables === []) {
        echo "(no tables)\n";
        exit(0);
    }

    foreach ($tables as $table) {
        $escaped = str_replace('"', '""', (string) $table);
        echo "{$table}\n";
        $info = $pdo->query('PRAGMA table_info("' . $escaped . '")');
        $rows = $info->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $col) {
            $name = $col['name'] ?? '';
            $type = $col['type'] ?? '';
            $notnull = !empty($col['notnull']);
            $dflt = $col['dflt_value'];
            $pk = !empty($col['pk']);
            $dfltStr = $dflt === null ? 'NULL' : json_encode($dflt, JSON_UNESCAPED_UNICODE);
            echo sprintf(
                "  %-32s %-12s null=%s pk=%s default=%s\n",
                $name,
                $type,
                $notnull ? 'no' : 'yes',
                $pk ? 'yes' : 'no',
                $dfltStr
            );
        }
        echo "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
