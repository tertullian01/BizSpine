<?php

namespace App\Controllers;

use App\Services\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController extends ApiController
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function index(Request $request, Response $response): Response
    {
        // Check if database is initialized
        $dbPath = $this->config['database']['database_path'] ?? null;

        // If no path configured, assume default location
        if (!$dbPath) {
            $dbPath = __DIR__ . '/../../protected/db/database.sqlite';
        }

        // Check if database file exists and has tables
        if (!file_exists($dbPath)) {
            $setupUrl = $this->getSetupUrl($request);
            return $this->error(
                $response,
                "Database not initialized. Please run the setup: {$setupUrl}",
                503
            );
        }

        try {
            $db = Database::get($dbPath);

            // Check if essential tables exist
            $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            $hasUsers = $stmt->fetch();

            if (!$hasUsers) {
                $setupUrl = $this->getSetupUrl($request);
                return $this->error(
                    $response,
                    "Database exists but is not initialized. Please run the setup: {$setupUrl}",
                    503
                );
            }

            // Database is healthy
            return $this->success($response, [
                'status' => 'ok',
                'time' => date(DATE_ATOM),
                'app' => 'BizSpine API',
                'database' => 'initialized'
            ]);
        } catch (\Exception $e) {
            return $this->error(
                $response,
                'Database error: ' . $e->getMessage(),
                500
            );
        }
    }

    private function getSetupUrl(Request $request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        // Build base URL (scheme://host:port)
        $baseUrl = $scheme . '://' . $host;
        if ($port && (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))) {
            $baseUrl .= ':' . $port;
        }

        // Get the path and strip /api or /health to find the root
        $path = $uri->getPath();

        // Remove common API paths to get to root
        $path = preg_replace('#/(api|health).*$#', '', $path);

        // If we still have a path (like /subdirectory), keep it
        if ($path && $path !== '/') {
            $baseUrl .= $path;
        }

        return $baseUrl . '/setup.html';
    }
}
