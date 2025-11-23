<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\Validator;
use App\Services\EmailService;
use Firebase\JWT\JWT;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class AuthController extends ApiController
{
    private array $config;
    private PDO $db;
    private Validator $validator;
    private EmailService $emailService;

    public function __construct(array $config = [], ?EmailService $emailService = null)
    {
        $this->config = $config;
        $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
        $this->db = Database::get($dbPath);
        $this->validator = new Validator();
        $this->emailService = $emailService ?? new EmailService($config['email'] ?? [], new \App\Services\Logger());
    }

    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        try {
            $this->validator->validate($body, [
                'email' => v::notEmpty()->email()->setName('Email'),
                'password' => v::notEmpty()->length(8, null)->setName('Password'),
            ]);
        } catch (ValidationException $e) {
            return $this->error($response, $e->getFirstError(), 400);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 400);
        }

        $email = trim($body['email']);
        $password = trim($body['password']);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (:e,:p,datetime("now"))');
        try {
            $stmt->execute([':e' => $email, ':p' => $hash]);
            return $this->success($response, ['message' => 'User created', 'email' => $email], 201);
        } catch (\PDOException $ex) {
            return $this->error($response, 'User already exists', 409);
        }
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        error_log("LOGIN: Attempting login for email: $email");

        // Basic validation, can be enhanced
        if (empty($email) || empty($password)) {
            error_log("LOGIN: Validation failed - missing email or password");
            return $this->error($response, 'Email and password are required', 400);
        }

        error_log("LOGIN: Validation passed, querying database");
        $stmt = $this->db->prepare('SELECT id, password_hash, role FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("LOGIN: User not found: $email");
            return $this->error($response, 'Invalid credentials', 401);
        }

        error_log("LOGIN: User found, verifying password");
        if (!password_verify($password, $user['password_hash'])) {
            error_log("LOGIN: Password verification failed for user: $email");
            return $this->error($response, 'Invalid credentials', 401);
        }

        error_log("LOGIN: Authentication successful, generating token");
        $now = time();
        $payload = [
            'iss' => $this->config['jwt']['issuer'] ?? 'local',
            'iat' => $now,
            'exp' => $now + ($this->config['jwt']['access_exp'] ?? 900),
            'sub' => (string)$user['id'],
            'role' => $user['role'] ?? 'customer',
        ];
        $token = JWT::encode($payload, $this->config['jwt']['secret'] ?? 'dev', 'HS256');

        error_log("LOGIN: Login successful for user: $email");
        return $this->success($response, ['access_token' => $token]);
    }

    public function logout(Request $request, Response $response): Response
    {
        error_log("LOGOUT: Logout attempt started");

        $userId = $request->getAttribute('user_id');
        error_log("LOGOUT: User ID from token: " . ($userId ?? 'null'));

        if (!$userId) {
            error_log("LOGOUT: Missing user_id attribute - authentication failed");
            throw new ValidationException('Missing user_id');
        }

        // Mark refresh tokens as revoked for this user (optional implementation)
        error_log("LOGOUT: Logout successful for user ID: $userId");
        return $this->success($response, ['message' => 'Logged out successfully']);
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
        return $this->success($response, ['access_token' => $token]);
    }

    public function oauthRedirect(Request $request, Response $response): Response
    {
        return $this->error($response, 'Not implemented', 501);
    }

    public function oauthCallback(Request $request, Response $response): Response
    {
        return $this->error($response, 'Not implemented', 501);
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $this->validator->validate($body, [
            'email' => v::notEmpty()->email()->setName('Email'),
        ]);
        $email = trim($body['email']);

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            // For security, don't reveal if email exists
            return $this->success($response, ['message' => 'If the email exists, a reset link has been sent.']);
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $stmt = $this->db->prepare('UPDATE users SET reset_token = :t, reset_expires_at = :e WHERE id = :id');
        $stmt->execute([':t' => $token, ':e' => $expiresAt, ':id' => $user['id']]);

        // Send email
        $emailSent = $this->emailService->sendPasswordResetEmail($email, $token);

        if (!$emailSent) {
            // Log error, but still return success for security
            return $this->success($response, ['message' => 'If the email exists, a reset link has been sent.']);
        }

        return $this->success($response, ['message' => 'If the email exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        error_log("RESET_PASSWORD: Password reset attempt started");

        $body = $request->getParsedBody();
        error_log("RESET_PASSWORD: Request body received");

        try {
            $this->validator->validate($body, [
                'token' => v::notEmpty()->setName('Token'),
                'password' => v::notEmpty()->length(8, null)->setName('Password'),
            ]);
            error_log("RESET_PASSWORD: Validation passed");
        } catch (\Exception $e) {
            error_log("RESET_PASSWORD: Validation failed: " . $e->getMessage());
            return $this->error($response, $e->getMessage(), 400);
        }

        $token = trim($body['token']);
        $password = trim($body['password']);
        error_log("RESET_PASSWORD: Processing token: " . substr($token, 0, 10) . "...");

        error_log("RESET_PASSWORD: Checking token in database");
        $stmt = $this->db->prepare('SELECT id, reset_expires_at FROM users WHERE reset_token = :t');
        $stmt->execute([':t' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("RESET_PASSWORD: Token not found in database");
            return $this->error($response, 'Invalid or expired token', 400);
        }

        error_log("RESET_PASSWORD: Token found, expires at: " . $user['reset_expires_at']);
        $now = date('Y-m-d H:i:s');
        error_log("RESET_PASSWORD: Current time: " . $now);

        if (strtotime($user['reset_expires_at']) < time()) {
            error_log("RESET_PASSWORD: Token has expired");
            return $this->error($response, 'Invalid or expired token', 400);
        }

        error_log("RESET_PASSWORD: Token is valid and not expired");

        error_log("RESET_PASSWORD: Valid token found for user ID: " . $user['id']);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        error_log("RESET_PASSWORD: Password hash generated");

        $stmt = $this->db->prepare('UPDATE users SET password_hash = :p, reset_token = NULL, reset_expires_at = NULL WHERE id = :id');
        $stmt->execute([':p' => $hash, ':id' => $user['id']]);

        error_log("RESET_PASSWORD: Password reset successful for user ID: " . $user['id']);
        return $this->success($response, ['message' => 'Password reset successfully']);
    }
}
