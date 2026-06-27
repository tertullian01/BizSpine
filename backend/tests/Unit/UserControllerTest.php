<?php

namespace Tests\Unit;

use App\Controllers\UserController;
use Tests\DatabaseTestCase;
use PDO;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class UserControllerTest extends DatabaseTestCase
{
    public function testCreateUserWithDuplicateEmail(): void
    {
        $stmt = self::$db->prepare('INSERT INTO users (email, password_hash, role) VALUES (:e, :p, :r)');
        $stmt->execute([
            ':e' => 'existing@example.com',
            ':p' => password_hash('password123', PASSWORD_DEFAULT),
            ':r' => 'customer',
        ]);

        $controller = new UserController();
        $request = $this->createRequestWithBody('POST', '/api/users', [
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role' => 'customer',
        ]);
        $response = $controller->createUser($request, $this->createResponse());

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertEquals('User already exists', $body['error']);

        $stmt = self::$db->query("SELECT COUNT(*) FROM users WHERE email = 'existing@example.com'");
        $this->assertEquals(1, $stmt->fetchColumn());
    }
}
