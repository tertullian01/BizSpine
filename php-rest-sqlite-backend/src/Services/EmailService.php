<?php

namespace App\Services;

use PDO;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    private PDO $db;
    private ?Logger $logger;
    private ?array $settings = null;

    public function __construct(PDO $db, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    private function getSettings(): array
    {
        if ($this->settings === null) {
            $stmt = $this->db->query("SELECT key, value FROM settings WHERE group_name = 'email'");
            $this->settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        return $this->settings;
    }

    public function send(string $to, string $subject, string $body, bool $isHtml = true, bool $debug = false): void
    {
        $settings = $this->getSettings();

        if ($this->logger) {
            $this->logger->info("Attempting to send email", [
                'to' => $to,
                'subject' => $subject,
                'smtp_host' => $settings['smtp_host'] ?? 'not set',
                'smtp_port' => $settings['smtp_port'] ?? 'not set',
                'smtp_user' => $settings['smtp_username'] ?? 'not set',
                'smtp_enc' => $settings['smtp_encryption'] ?? 'not set',
                'from' => $settings['from_email'] ?? 'not set'
            ]);
        }

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            throw new \Exception('PHPMailer library not found');
        }

        $mail = new PHPMailer(true);
        
        if ($this->logger && $debug) {
            $mail->SMTPDebug = 2; // Enable verbose debug output
            $mail->Debugoutput = function($str, $level) {
                $this->logger->info("PHPMailer: " . trim($str));
            };
        }

        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'] ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_username'] ?? '';
        $mail->Password   = $this->decrypt($settings['smtp_password'] ?? '');
        $mail->SMTPSecure = $settings['smtp_encryption'] ?? 'tls';
        $mail->Port       = $settings['smtp_port'] ?? 587;
        
        $fromEmail = $settings['from_email'] ?? 'noreply@example.com';
        $fromName = $settings['from_name'] ?? 'Store Admin';
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }
        
        $mail->send();
    }

    public function sendPasswordResetEmail(string $to, string $token): bool
    {
        // In a real app, you would use a frontend URL from config
        $resetLink = "http://localhost:3000/reset-password?token=" . $token;
        $subject = "Password Reset Request";
        $body = "Click the following link to reset your password: <a href='{$resetLink}'>{$resetLink}</a>";

        try {
            $this->send($to, $subject, $body);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function decrypt(string $value): string
    {
        if (strpos($value, 'ENC:') !== 0) {
            return $value;
        }
        
        $key = Config::getInstance()->get('jwt.secret') ?? 'default_secret';
        $payload = base64_decode(substr($value, 4));
        if (!$payload || strpos($payload, '::') === false) return $value;
        
        list($encrypted_data, $iv) = explode('::', $payload, 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv) ?: $value;
    }
}