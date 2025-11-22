<?php
namespace App\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        $email = trim($body['email'] ?? '');
        $password = trim($body['password'] ?? '');
        
        try {
            $user = User::register($email, $password);
            $response->getBody()->write(json_encode(['message' => 'User created', 'email' => $user->email]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
        
        $user = User::findByEmail($email);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $token = $user->login($password, $this->config);
        if (!$token) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $response->getBody()->write(json_encode(['access_token' => $token]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logout(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Missing user_id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        // Mark refresh tokens as revoked for this user (optional implementation)
        $response->getBody()->write(json_encode(['message' => 'Logged out successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function refresh(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Missing user_id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
        $response->getBody()->write(json_encode(['error'=>'Not implemented']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(501);
    }

    public function oauthCallback(Request $request, Response $response): Response
    { 
        $response->getBody()->write(json_encode(['error'=>'Not implemented']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(501);
    }
}