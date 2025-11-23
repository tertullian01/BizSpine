<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private array $config;
    private Logger $logger;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function sendPasswordResetEmail(string $toEmail, string $resetToken): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Port = $this->config['smtp']['port'];

            // Recipients
            $mail->setFrom($this->config['from']['email'], $this->config['from']['name']);
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = $this->getPasswordResetEmailBody($resetToken);
            $mail->AltBody = $this->getPasswordResetEmailAltBody($resetToken);

            $mail->send();
            $this->logger->info('Password reset email sent', ['to' => $toEmail]);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'to' => $toEmail,
                'error' => $mail->ErrorInfo
            ]);
            return false;
        }
    }

    private function getPasswordResetEmailBody(string $token): string
    {
        return "
        <html>
        <head>
            <title>Password Reset</title>
        </head>
        <body>
            <h2>Password Reset Request</h2>
            <p>You have requested to reset your password. Click the link below to reset it:</p>
            <p><a href='https://nakednettle.com/reset.html?token={$token}'>Reset Password</a></p>
            <p>If you didn't request this, please ignore this email.</p>
            <p>This link will expire in 1 hour.</p>
        </body>
        </html>
        ";
    }

    private function getPasswordResetEmailAltBody(string $token): string
    {
        return "Password Reset Request\n\nYou have requested to reset your password. Use this token: {$token}\n\nIf you didn't request this, please ignore this email.\n\nThis token will expire in 1 hour.";
    }
}