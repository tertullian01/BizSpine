<?php

namespace App\Services;

use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use App\Models\EmailLog;

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

    public function send(string $to, string $subject, string $body, bool $isHtml = true, bool $debug = false, ?string $replyTo = null): void
    {
        $settings = $this->getSettings();
        $password = $this->decrypt($settings['smtp_password'] ?? '');

        if ($this->logger) {
            $context = [
                'to' => $to,
                'subject' => $subject,
                'smtp_host' => $settings['smtp_host'] ?? 'not set',
                'smtp_port' => $settings['smtp_port'] ?? 'not set',
                'smtp_enc' => $settings['smtp_encryption'] ?? 'not set',
                'from' => $settings['from_email'] ?? 'not set'
            ];
            $this->logger->info("Attempting to send email", $context);
        }

        // Use default reply-to from settings if not provided
        if (empty($replyTo) && !empty($settings['reply_to_email'])) {
            $replyTo = $settings['reply_to_email'];
        }

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            throw new \Exception('PHPMailer library not found');
        }

        // Create log entry
        $emailLog = new EmailLog([
            'recipient' => $to,
            'subject' => $subject,
            'body' => $body,
            'status' => 'pending',
            'reply_to' => $replyTo,
            'sent_at' => date('Y-m-d H:i:s')
        ]);
        $emailLog->save();

        try {
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
            $mail->Password   = $password;
            $mail->SMTPSecure = $settings['smtp_encryption'] ?? 'tls';
            $mail->Port       = $settings['smtp_port'] ?? 587;
            
            $fromEmail = $settings['from_email'] ?? 'noreply@example.com';
            $fromName = $settings['from_name'] ?? 'Store Admin';
            
            $mail->setFrom($fromEmail, $fromName);
            if (!empty($replyTo)) {
                $mail->addReplyTo($replyTo);
            }

            $mail->addAddress($to);
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            
            if ($isHtml) {
                $mail->AltBody = strip_tags($body);
            }
            
            $mail->send();

            $emailLog->status = 'sent';
            $emailLog->save();
        } catch (\Exception $e) {
            $emailLog->status = 'failed';
            $emailLog->error_message = $e->getMessage();
            $emailLog->save();
            throw $e;
        }
    }

    public function sendTemplate(string $to, string $templateName, array $placeholders, ?int $storeId = null, ?string $replyTo = null): void
    {
        // Try to find a store-specific template first, fallback to default (store_id IS NULL)
        // SQLite sorts NULLs first in ASC, so DESC puts specific IDs before NULLs.
        $sql = "SELECT subject, body FROM email_templates WHERE name = :name AND (store_id = :store_id OR store_id IS NULL) ORDER BY store_id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $templateName, ':store_id' => $storeId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            // Fallback to logging error, or throw exception. 
            // For now, we throw to alert the caller that configuration is missing.
            throw new \Exception("Email template '$templateName' not found.");
        }

        $subject = $this->replacePlaceholders($template['subject'], $placeholders);
        $body = $this->replacePlaceholders($template['body'], $placeholders);

        $this->send($to, $subject, $body, true, false, $replyTo);
    }

    public function sendPasswordResetEmail(string $to, string $token): bool
    {
        // In a real app, you would use a frontend URL from config
        $resetLink = "http://localhost:3000/reset-password?token=" . $token;
        $subject = "Password Reset Request";
        $body = "Click the following link to reset your password: <a href='{$resetLink}'>{$resetLink}</a>";

        try {
            $this->sendTemplate($to, 'password_reset', ['reset_link' => $resetLink]);
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
        
        $key = \App\Routes\RouteSecurity::jwtSecret();
        $payload = base64_decode(substr($value, 4));
        if (!$payload || strpos($payload, '::') === false) return $value;
        
        list($encrypted_data, $iv) = explode('::', $payload, 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv) ?: $value;
    }

    private function replacePlaceholders(string $content, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string)$value, $content);
        }
        return $content;
    }
}