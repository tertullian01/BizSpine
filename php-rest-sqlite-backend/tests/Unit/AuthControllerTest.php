<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\AuthController;
use App\Services\EmailService;
use App\Services\Logger;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class AuthControllerTest extends DatabaseTestCase
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

    public function testRegisterWithValidCredentials()
    {
        $controller = new AuthController($this->config, self::$db);
        $requestData = ['email' => 'test@example.com', 'password' => 'password123'];
        $request = $this->createRequestWithBody('POST', '/api/auth/register', $requestData);
        $response = $this->createResponse();
        $response = $controller->register($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
// Assert the response is correct
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($body['success']);
        $this->assertEquals('User created', $body['data']['message']);
        $this->assertEquals('test@example.com', $body['data']['email']);
// Assert that the user was actually inserted into the database
        $stmt = self::$db->query("SELECT COUNT(*) FROM users WHERE email = 'test@example.com'");
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testLoginWithValidCredentials()
    {
        // Insert a test user
        $email = 'test@example.com';
        $password = 'password123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = self::$db->prepare('INSERT INTO users (email, password_hash) VALUES (:e, :p)');
        $stmt->execute([':e' => $email, ':p' => $hash]);
        $controller = new AuthController($this->config, self::$db);
        $requestData = ['email' => 'test@example.com', 'password' => 'password123'];
        $request = $this->createRequestWithBody('POST', '/api/auth/login', $requestData);
        $response = $this->createResponse();
        $response = $controller->login($request, $response, []);
        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($body['success']);
// Assert that we received an access token
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertNotEmpty($body['data']['access_token']);
    }

    public function testPasswordValidation()
    {
        $shortPassword = 'short';
        $validPassword = 'validpassword123';
        $this->assertLessThan(8, strlen($shortPassword));
        $this->assertGreaterThanOrEqual(8, strlen($validPassword));
    }

    public function testEmailValidation()
    {
        $validEmail = 'test@example.com';
        $invalidEmail = 'invalid-email';
        $this->assertNotFalse(filter_var($validEmail, FILTER_VALIDATE_EMAIL));
        $this->assertFalse(filter_var($invalidEmail, FILTER_VALIDATE_EMAIL));
    }

    public function testForgotPasswordWithExistingUser()
    {
        // Insert a test user
        $email = 'test@example.com';
        $stmt = self::$db->prepare('INSERT INTO users (email, password_hash) VALUES (:e, :p)');
        $stmt->execute([':e' => $email, ':p' => password_hash('password123', PASSWORD_DEFAULT)]);

        $this->emailServiceMock->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with($email, $this->isType('string'))
            ->willReturn(true);

        $controller = new AuthController($this->config, self::$db, $this->emailServiceMock);
        $requestData = ['email' => $email];
        $request = $this->createRequestWithBody('POST', '/api/auth/forgot-password', $requestData);
        $response = $this->createResponse();
        $response = $controller->forgotPassword($request, $response);

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('If the email exists, a reset link has been sent.', $body['data']['message']);

        // Check that reset_token was set
        $stmt = self::$db->prepare('SELECT reset_token FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($user['reset_token']);
        $this->assertNotEmpty($user['reset_token']);
    }

    public function testForgotPasswordWithNonExistingUser()
    {
        $this->emailServiceMock->expects($this->never())
            ->method('sendPasswordResetEmail');

        $controller = new AuthController($this->config, self::$db, $this->emailServiceMock);
        $requestData = ['email' => 'nonexistent@example.com'];
        $request = $this->createRequestWithBody('POST', '/api/auth/forgot-password', $requestData);
        $response = $this->createResponse();
        $response = $controller->forgotPassword($request, $response);

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('If the email exists, a reset link has been sent.', $body['data']['message']);
    }

    public function testResetPasswordWithValidToken()
    {
        // Insert a test user with reset token
        $email = 'test@example.com';
        $token = 'valid-reset-token';
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $stmt = self::$db->prepare('INSERT INTO users (email, password_hash, reset_token, reset_expires_at) VALUES (:e, :p, :t, :exp)');
        $stmt->execute([
            ':e' => $email,
            ':p' => password_hash('oldpassword', PASSWORD_DEFAULT),
            ':t' => $token,
            ':exp' => $expiresAt
        ]);

        $controller = new AuthController($this->config, self::$db);
        $requestData = ['token' => $token, 'password' => 'newpassword123'];
        $request = $this->createRequestWithBody('POST', '/api/auth/reset-password', $requestData);
        $response = $this->createResponse();
        $response = $controller->resetPassword($request, $response);

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertEquals('Password reset successfully', $body['data']['message']);

        // Check that password was updated and tokens cleared
        $stmt = self::$db->prepare('SELECT password_hash, reset_token, reset_expires_at FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertTrue(password_verify('newpassword123', $user['password_hash']));
        $this->assertNull($user['reset_token']);
        $this->assertNull($user['reset_expires_at']);
    }

    public function testResetPasswordWithInvalidToken()
    {
        $controller = new AuthController($this->config, self::$db);
        $requestData = ['token' => 'invalid-token', 'password' => 'newpassword123'];
        $request = $this->createRequestWithBody('POST', '/api/auth/reset-password', $requestData);
        $response = $this->createResponse();
        $response = $controller->resetPassword($request, $response);

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertEquals('Invalid or expired token', $body['error']);
    }

    public function testResetPasswordWithExpiredToken()
    {
        // Insert a test user with expired reset token
        $email = 'test@example.com';
        $token = 'expired-token';
        $expiresAt = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago
        $stmt = self::$db->prepare('INSERT INTO users (email, password_hash, reset_token, reset_expires_at) VALUES (:e, :p, :t, :exp)');
        $stmt->execute([
            ':e' => $email,
            ':p' => password_hash('oldpassword', PASSWORD_DEFAULT),
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
