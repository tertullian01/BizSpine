<?php

namespace App\Controllers;

use App\Services\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends ApiController
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $config = require __DIR__ . '/../../protected/config/config.php';
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
    }

    public function getAll(Request $request, Response $response): Response
    {
        // Check if user is admin for full access, otherwise filter by is_public
        $userId = $request->getAttribute('user_id');
        $isAdmin = false;
        
        if ($userId) {
            $stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $role = $stmt->fetchColumn();
            $isAdmin = ($role === 'admin');
        }

        $sql = "SELECT * FROM settings";
        if (!$isAdmin) {
            $sql .= " WHERE is_public = 1";
        }
        $sql .= " ORDER BY group_name, key";

        $stmt = $this->db->query($sql);
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group settings by group_name
        $grouped = [];
        foreach ($settings as $setting) {
            $group = $setting['group_name'] ?? 'general';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $setting;
        }

        return $this->success($response, $grouped);
    }

    public function update(Request $request, Response $response): Response
    {
        // Admin only
        $userId = $request->getAttribute('user_id');
        $stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $role = $stmt->fetchColumn();

        if ($role !== 'admin') {
            return $this->error($response, 'Unauthorized. Admin access required.', 403);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->error($response, 'Invalid request body', 400);
        }

        try {
            $this->db->beginTransaction();

            foreach ($body as $key => $data) {
                // Check if setting exists
                $stmt = $this->db->prepare('SELECT id FROM settings WHERE key = :key');
                $stmt->execute([':key' => $key]);
                
                if ($stmt->fetch()) {
                    // Update
                    $sql = 'UPDATE settings SET value = :value, updated_at = datetime("now") WHERE key = :key';
                    $updateStmt = $this->db->prepare($sql);
                    $updateStmt->execute([
                        ':value' => is_array($data) ? $data['value'] : $data,
                        ':key' => $key
                    ]);
                } else {
                    // Create (if passing full object)
                    if (is_array($data) && isset($data['value'])) {
                        $sql = 'INSERT INTO settings (key, value, type, group_name, description, is_public) VALUES (:key, :value, :type, :group_name, :description, :is_public)';
                        $insertStmt = $this->db->prepare($sql);
                        $insertStmt->execute([
                            ':key' => $key,
                            ':value' => $data['value'],
                            ':type' => $data['type'] ?? 'string',
                            ':group_name' => $data['group_name'] ?? 'general',
                            ':description' => $data['description'] ?? null,
                            ':is_public' => $data['is_public'] ?? 0
                        ]);
                    }
                }
            }

            $this->db->commit();
            return $this->success($response, ['message' => 'Settings updated successfully']);

        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->error($response, 'Database error: ' . $e->getMessage(), 500);
        }
    }
}