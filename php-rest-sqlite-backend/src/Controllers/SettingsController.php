<?php

namespace App\Controllers;

use App\Services\Database;
use App\Services\EmailService;
use App\Services\Config;
use App\Services\FileUploadService;
use App\Services\Logger;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends ApiController
{
    private PDO $db;
    private ?FileUploadService $fileUploadService;
    private EmailService $emailService;
    private ?Logger $logger;

    public function __construct(?PDO $db = null, ?FileUploadService $fileUploadService = null, ?EmailService $emailService = null, ?Logger $logger = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $config = require __DIR__ . '/../../protected/config/config.php';
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
        $this->fileUploadService = $fileUploadService;
        $this->logger = $logger;
        $this->emailService = $emailService ?: new EmailService($this->db, $this->logger);
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

            if ($setting['key'] === 'smtp_password' && !empty($setting['value'])) {
                $setting['value'] = '********';
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
                // Handle password encryption
                if ($key === 'smtp_password') {
                    $plainValue = is_array($data) ? $data['value'] : $data;
                    if ($plainValue === '********') {
                        continue; // Skip update if masked value is sent back
                    }
                    $encryptedValue = $this->encrypt($plainValue);
                    if (is_array($data)) {
                        $data['value'] = $encryptedValue;
                    } else {
                        $data = $encryptedValue;
                    }
                }

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
                    // Create
                    $value = is_array($data) ? ($data['value'] ?? null) : $data;

                    if ($value !== null) {
                        $type = is_array($data) ? ($data['type'] ?? 'string') : 'string';
                        $groupName = is_array($data) ? ($data['group_name'] ?? 'general') : 'general';

                        // Auto-assign group for email settings
                        if (!is_array($data) && (strpos($key, 'smtp_') === 0 || strpos($key, 'email_') === 0 || strpos($key, 'from_') === 0)) {
                            $groupName = 'email';
                        }

                        $sql = 'INSERT INTO settings (key, value, type, group_name, description, is_public, created_at, updated_at) VALUES (:key, :value, :type, :group_name, :description, :is_public, datetime("now"), datetime("now"))';
                        $insertStmt = $this->db->prepare($sql);
                        $insertStmt->execute([
                            ':key' => $key,
                            ':value' => $value,
                            ':type' => $type,
                            ':group_name' => $groupName,
                            ':description' => is_array($data) ? ($data['description'] ?? null) : null,
                            ':is_public' => is_array($data) ? ($data['is_public'] ?? 0) : 0
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
                // Sanitize filename to prevent directory traversal
                $filename = basename($filename);

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

    public function sendVerificationEmail(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $role = $stmt->fetchColumn();

        if ($role !== 'admin') {
            return $this->error($response, 'Unauthorized. Admin access required.', 403);
        }

        $stmt = $this->db->query("SELECT value FROM settings WHERE key = 'store_email'");
        $storeEmail = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT value FROM settings WHERE key = 'from_email'");
        $fromEmail = $stmt->fetchColumn();
        
        $recipient = $storeEmail ?: $fromEmail;

        if (!$recipient) {
            return $this->error($response, 'No recipient email configured (store_email or from_email)', 400);
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $this->saveSettingValue('email_verification_code', $code, 'hidden');
        $this->saveSettingValue('email_verification_expiry', $expiry, 'hidden');

        try {
            if ($this->logger) {
                $this->logger->info("Sending verification email to: " . $recipient);
            }
            $this->emailService->send($recipient, 'Email Verification', "Your verification code is: $code", true);
            return $this->success($response, ['message' => 'Verification email sent to ' . $recipient]);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to send verification email: " . $e->getMessage());
            }
            
            $errorMessage = $e->getMessage();
            $statusCode = 500;

            if (str_contains($errorMessage, 'Could not authenticate')) {
                $statusCode = 400;
                $errorMessage = 'SMTP Authentication failed. If using Gmail, you must use an App Password, not your login password.';
            }

            return $this->error($response, $errorMessage, $statusCode);
        }
    }

    public function verifyEmailCode(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $role = $stmt->fetchColumn();

        if ($role !== 'admin') {
            return $this->error($response, 'Unauthorized. Admin access required.', 403);
        }

        $body = $request->getParsedBody();
        $code = $body['code'] ?? '';

        if (empty($code)) {
            return $this->error($response, 'Code is required', 400);
        }

        $stmt = $this->db->query("SELECT key, value FROM settings WHERE key IN ('email_verification_code', 'email_verification_expiry')");
        $data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if (!isset($data['email_verification_code']) || !isset($data['email_verification_expiry'])) {
            return $this->error($response, 'No verification pending', 400);
        }

        if (time() > strtotime($data['email_verification_expiry'])) {
            return $this->error($response, 'Verification code expired', 400);
        }

        if ($data['email_verification_code'] !== $code) {
            return $this->error($response, 'Invalid verification code', 400);
        }

        $this->db->exec("DELETE FROM settings WHERE key IN ('email_verification_code', 'email_verification_expiry')");

        return $this->success($response, ['message' => 'Email verified successfully']);
    }

    private function saveSettingValue(string $key, string $value, string $group = 'general'): void
    {
        $sql = "INSERT INTO settings (key, value, type, group_name, is_public, created_at, updated_at) 
                VALUES (:key, :value, 'string', :group, 0, datetime('now'), datetime('now'))
                ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':key' => $key, ':value' => $value, ':group' => $group]);
    }

    private function encrypt(string $value): string
    {
        $key = Config::getInstance()->get('jwt.secret') ?? 'default_secret';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);
        return 'ENC:' . base64_encode($encrypted . '::' . $iv);
    }
}