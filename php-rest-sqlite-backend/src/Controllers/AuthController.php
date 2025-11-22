<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\Validator;
use Firebase\JWT\JWT;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class AuthController
{
    private array $config;
    private PDO $db;
    private Validator $validator;
    public function __construct(array $config = [], ?PDO $db = null)
    {
        $this->config = $config;
        if ($db) {
            $this->db = $db;
        } else {
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
        $this->validator = new Validator();
    }

    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $this->validator->validate($body, [
            'email' => v::notEmpty()->email()->setName('Email'),
            'password' => v::notEmpty()->length(8, null)->setName('Password'),
        ]);
        $email = trim($body['email']);
        $password = trim($body['password']);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (:e,:p,datetime("now"))');
        try {
            $stmt->execute([':e' => $email, ':p' => $hash]);
            $response->getBody()->write(json_encode(['message' => 'User created', 'email' => $email]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\PDOException $ex) {
            $response->getBody()->write(json_encode(['error' => 'User already exists']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        // This could be handled by the error handler too
        }
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
// Basic validation, can be enhanced
        if (empty($email) || empty($password)) {
            throw new ValidationException("Email and password are required.");
        }

        $stmt = $this->db->prepare('SELECT id, password_hash, role FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password_hash'])) {
        // Using a generic exception for auth failures
            throw new \Exception('Invalid credentials', 401);
        }

        $now = time();
        $payload = [
            'iss' => $this->config['jwt']['issuer'] ?? 'local',
            'iat' => $now,
            'exp' => $now + ($this->config['jwt']['access_exp'] ?? 900),
            'sub' => (string)$user['id'],
            'role' => $user['role'] ?? 'customer',
        ];
        $token = JWT::encode($payload, $this->config['jwt']['secret'] ?? 'dev', 'HS256');
        $response->getBody()->write(json_encode(['access_token' => $token]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logout(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            throw new ValidationException('Missing user_id');
        }
        // Mark refresh tokens as revoked for this user (optional implementation)
        $response->getBody()->write(json_encode(['message' => 'Logged out successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function refresh(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            throw new ValidationException('Missing user_id');
        }
        // Issue new access token
        $now = time();
        $payload = [
            'iss' => $this->config['jwt']['issuer'] ?? 'local',
            'iat' => $now,
            'exp' => $now + ($this->config['jwt']['access_exp'] ?? 900),
            'sub' => (string)$userId,
        ];
        $token = JWT::encode($payload, $this->config['jwt']['secret'] ?? 'dev', 'HS256');
        $response->getBody()->write(json_encode(['access_token' => $token]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function oauthRedirect(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode(['error' => 'Not implemented']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(501);
    }

    public function oauthCallback(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode(['error' => 'Not implemented']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(501);
    }
}
