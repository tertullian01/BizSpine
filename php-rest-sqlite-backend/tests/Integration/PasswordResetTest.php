<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;
use App\Controllers\AuthController;
use App\Services\EmailService;
use App\Services\Logger;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;

class PasswordResetTest extends DatabaseTestCase
{
    private array $config;
    private EmailService&MockObject $emailServiceMock;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = [
            'jwt' => [
                'secret' => 'test-secret-key',
                'issuer' => 'test.local',
                'access_exp' => 900,
                'refresh_exp' => 604800,
            ]
        ];
        $this->logger = new Logger();
        $this->emailServiceMock = $this->createMock(EmailService::class);
    }

    public function testForgotPasswordEndpointIntegration()
    {
        // Insert a test user
        $email = 'test@example.com';
        $stmt = self::$db->prepare('INSERT INTO users (email, password_hash) VALUES (:e, :p)');
        $stmt->execute([':e' => $email, ':p' => password_hash('password123', PASSWORD_DEFAULT)]);

        $this->emailServiceMock->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with($email, $this->isType('string'))
            ->willReturn(true);

        // Simulate the full request flow
        $controller = new AuthController($this->config, self::$db, $this->emailServiceMock);
        $requestData = ['email' => $email];
        $request = $this->createRequestWithBody('POST', '/api/auth/forgot-password', $requestData);
        $response = $this->createResponse();
        $response = $controller->forgotPassword($request, $response);

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('If the email exists, a reset link has been sent.', $body['data']['message']);

        // Verify database state
        $stmt = self::$db->prepare('SELECT reset_token, reset_expires_at FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($user['reset_token']);
        $this->assertNotNull($user['reset_expires_at']);

        // Verify token is valid format (64 character hex)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $user['reset_token']);
    }

    public function testResetPasswordEndpointIntegration()
    {
        // Insert a test user with reset token
        $email = 'test@example.com';
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $oldPassword = password_hash('oldpassword', PASSWORD_DEFAULT);

        $stmt = self::$db->prepare('INSERT INTO users (email, password_hash, reset_token, reset_expires_at) VALUES (:e, :p, :t, :exp)');
        $stmt->execute([
            ':e' => $email,
            ':p' => $oldPassword,
            ':t' => $token,
            ':exp' => $expiresAt
        ]);

        // Simulate password reset
        $controller = new AuthController($this->config, self::$db);
        $requestData = ['token' => $token, 'password' => 'newSecurePassword123'];
        $request = $this->createRequestWithBody('POST', '/api/auth/reset-password', $requestData);
        $response = $this->createResponse();
        $response = $controller->resetPassword($request, $response);

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('Password reset successfully', $body['data']['message']);

        // Verify database state after reset
        $stmt = self::$db->prepare('SELECT password_hash, reset_token, reset_expires_at FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Password should be updated
        $this->assertTrue(password_verify('newSecurePassword123', $user['password_hash']));
        $this->assertNotEquals($oldPassword, $user['password_hash']);

        // Tokens should be cleared
        $this->assertNull($user['reset_token']);
        $this->assertNull($user['reset_expires_at']);
    }

    public function testResetPasswordWithExpiredToken()
    {
        // Insert a test user with expired token
        $email = 'test@example.com';
        $token = 'expired-token';
        $expiresAt = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago

        $stmt = self::$db->prepare('INSERT INTO users (email, password_hash, reset_token, reset_expires_at) VALUES (:e, :p, :t, :exp)');
        $stmt->execute([
            ':e' => $email,
            ':p' => password_hash('password123', PASSWORD_DEFAULT),
            ':t' => $token,
            ':exp' => $expiresAt
        ]);

        $controller = new AuthController($this->config, self::$db);
        $requestData = ['token' => $token, 'password' => 'newpassword123'];
        $request = $this->createRequestWithBody('POST', '/api/auth/reset-password', $requestData);
        $response = $this->createResponse();
        $response = $controller->resetPassword($request, $response);

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertEquals('Invalid or expired token', $body['error']);
    }
}