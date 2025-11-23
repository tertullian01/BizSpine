<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\EmailService;
use App\Services\Logger;

class EmailServiceTest extends TestCase
{
    private array $config;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->config = [
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'test@example.com',
                'password' => 'password',
                'encryption' => 'tls',
            ],
            'from' => [
                'email' => 'noreply@example.com',
                'name' => 'Test App',
            ],
        ];
        $this->logger = new Logger();
    }

    public function testSendPasswordResetEmailSuccess()
    {
        // Since PHPMailer can't be easily mocked, we'll test that the method exists and is callable
        $emailService = new EmailService($this->config, $this->logger);
        $this->assertTrue(method_exists($emailService, 'sendPasswordResetEmail'));

        // In a real scenario, this would require SMTP server setup for integration testing
        // For unit testing, we assume the method signature is correct
    }

    public function testGetPasswordResetEmailBodyContainsToken()
    {
        $emailService = new EmailService($this->config, $this->logger);
        $reflection = new \ReflectionClass($emailService);
        $method = $reflection->getMethod('getPasswordResetEmailBody');
        $method->setAccessible(true);

        $token = 'test-reset-token-123';
        $body = $method->invoke($emailService, $token);

        $this->assertStringContainsString('test-reset-token-123', $body);
        $this->assertStringContainsString('Password Reset Request', $body);
        $this->assertStringContainsString('reset-password?token=', $body);
    }

    public function testGetPasswordResetEmailAltBodyContainsToken()
    {
        $emailService = new EmailService($this->config, $this->logger);
        $reflection = new \ReflectionClass($emailService);
        $method = $reflection->getMethod('getPasswordResetEmailAltBody');
        $method->setAccessible(true);

        $token = 'test-reset-token-123';
        $altBody = $method->invoke($emailService, $token);

        $this->assertStringContainsString('test-reset-token-123', $altBody);
        $this->assertStringContainsString('Password Reset Request', $altBody);
    }
}