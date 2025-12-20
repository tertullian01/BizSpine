<?php

namespace App\Controllers;

use App\Services\Config;
use App\Models\Setting;
use App\Services\FileUploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends ApiController
{
    private FileUploadService $fileUploadService;

    public function __construct(?FileUploadService $fileUploadService = null)
    {
        $this->fileUploadService = $fileUploadService ?? new FileUploadService(new \App\Services\Logger());
    }

    public function getAll(Request $request, Response $response): Response
    {
        $settings = Setting::fetchAll("SELECT * FROM settings ORDER BY group_name, key");
        foreach ($settings as $setting) {
            if ($setting->key === 'smtp_password' && !empty($setting->value)) {
                $setting->value = '********';
            }
        }
        return $this->success($response, $settings);
    }

    public function update(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $updated = [];
        $emailSettings = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name'];

        foreach ($body as $key => $value) {
            // Skip empty password updates to avoid overwriting with empty string
            if ($key === 'smtp_password' && empty($value)) {
                continue;
            }

            $strValue = is_array($value) ? json_encode($value) : (string)$value;
            
            // Encrypt SMTP password
            if ($key === 'smtp_password') {
                $strValue = $this->encrypt($strValue);
            }

            $setting = Setting::fetchOne("SELECT * FROM settings WHERE key = :key", [':key' => $key]);
            
            $groupName = in_array($key, $emailSettings) ? 'email' : 'general';

            if ($setting) {
                $setting->value = $strValue;
                if (in_array($key, $emailSettings)) {
                    $setting->group_name = 'email';
                }
                $setting->updated_at = date('Y-m-d H:i:s');
                $setting->save();
            } else {
                $setting = new Setting([
                    'key' => $key,
                    'value' => $strValue,
                    'group_name' => $groupName,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $setting->save();
            }
            
            if ($key === 'smtp_password') {
                $updated[$key] = '********';
            } else {
                $updated[$key] = $setting->value;
            }
        }

        return $this->success($response, ['updated' => $updated]);
    }

    public function uploadLogo(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (!isset($uploadedFiles['logo'])) {
            return $this->error($response, 'No logo uploaded', 400);
        }

        try {
            $result = $this->fileUploadService->uploadFile($uploadedFiles['logo'], 'logo', [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'svg', 'webp'],
                'max_file_size' => 2 * 1024 * 1024 // 2MB
            ]);

            $key = 'site_logo';
            $setting = Setting::fetchOne("SELECT * FROM settings WHERE key = :key", [':key' => $key]);
            if (!$setting) {
                $setting = new Setting(['key' => $key, 'group_name' => 'appearance']);
            }
            $setting->value = $result['url'];
            $setting->updated_at = date('Y-m-d H:i:s');
            $setting->save();

            return $this->success($response, ['url' => $result['url']]);

        } catch (\Exception $e) {
            return $this->error($response, 'Upload failed: ' . $e->getMessage(), 400);
        }
    }

    private function encrypt(string $value): string
    {
        $config = Config::getInstance();
        $key = $config->get('jwt.secret') ?? 'default_secret_key';
        $cipher = "aes-256-cbc";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($value, $cipher, $key, 0, $iv);
        return base64_encode($iv . $ciphertext);
    }
}