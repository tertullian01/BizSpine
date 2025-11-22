<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\AuthController;
use PDO;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class AuthControllerTest extends DatabaseTestCase
{
    private array $config;
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
        $this->assertEquals('User created', $body['message']);
        $this->assertEquals('test@example.com', $body['email']);
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
// Assert that we received an access token
        $this->assertArrayHasKey('access_token', $body);
        $this->assertNotEmpty($body['access_token']);
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
}
