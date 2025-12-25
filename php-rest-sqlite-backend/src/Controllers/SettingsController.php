<?php

namespace App\Controllers;

use App\Services\Database;
use App\Services\FileUploadService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends ApiController
{
    private PDO $db;
    private ?FileUploadService $fileUploadService;

    public function __construct(?PDO $db = null, ?FileUploadService $fileUploadService = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $config = require __DIR__ . '/../../protected/config/config.php';
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
        $this->fileUploadService = $fileUploadService;
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
            // Base64 encode binary logo data to prevent json_encode errors
            if ($setting['key'] === 'store_logo' && $setting['type'] === 'image' && !empty($setting['value'])) {
                $setting['value'] = base64_encode($setting['value']);
            }

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

    public function uploadLogo(Request $request, Response $response): Response
    {
        // Admin only
        $userId = $request->getAttribute('user_id');
        $stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $role = $stmt->fetchColumn();

        if ($role !== 'admin') {
            return $this->error($response, 'Unauthorized. Admin access required.', 403);
        }

        $data = null;
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!empty($uploadedFiles['logo'])) {
            /** @var \Psr\Http\Message\UploadedFileInterface $uploadedFile */
            $uploadedFile = $uploadedFiles['logo'];
            if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                try {
                    $stream = $uploadedFile->getStream();
                    $stream->rewind();
                    $data = $stream->getContents();
                } catch (\RuntimeException $e) {
                    // File already moved by middleware, check parsed body for filename
                }
            }
        }

        if ($data === null) {
            $filename = null;
            $body = $request->getParsedBody();
            
            // Check parsed body for filename
            if (is_array($body) && isset($body['logo']) && is_string($body['logo'])) {
                $filename = $body['logo'];
            }
            
            // Check attributes for filename (fallback)
            if (!$filename) {
                $attr = $request->getAttribute('logo');
                if (is_string($attr)) {
                    $filename = $attr;
                }
            }
            
            // Check uploaded_files attribute (common middleware convention)
            if (!$filename) {
                $uploadedFilesAttr = $request->getAttribute('uploaded_files');
                if (is_array($uploadedFilesAttr) && isset($uploadedFilesAttr['logo'])) {
                    $fileInfo = $uploadedFilesAttr['logo'];
                    if (is_string($fileInfo)) {
                        $filename = $fileInfo;
                    } elseif (is_array($fileInfo)) {
                        if (isset($fileInfo[0]) && is_string($fileInfo[0])) {
                            $filename = $fileInfo[0];
                        } elseif (isset($fileInfo['filename']) && is_string($fileInfo['filename'])) {
                            $filename = $fileInfo['filename'];
                        }
                    }
                }
            }

            if ($filename && is_string($filename)) {
                $config = require __DIR__ . '/../../protected/config/config.php';
                $uploadPath = $config['file_upload']['upload_path'];
                $filePath = $uploadPath . $filename;
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    if ($content !== false) {
                        $data = $content;
                    }
                }
            }
        }

        if ($data === null) {
            return $this->error($response, 'No file uploaded', 400);
        }

        try {
            $sql = "INSERT INTO settings (key, value, type, group_name, description, is_public, created_at, updated_at) 
                    VALUES ('store_logo', :value, 'image', 'general', 'Store Logo', 1, datetime('now'), datetime('now'))
                    ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':value', $data, PDO::PARAM_LOB);
            $stmt->execute();

            return $this->success($response, ['message' => 'Logo uploaded successfully']);
        } catch (\Exception $e) {
            return $this->error($response, 'Database error: ' . $e->getMessage(), 500);
        }
    }
}